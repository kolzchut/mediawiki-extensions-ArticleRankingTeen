( function () {
	'use strict';

	// Rendered explicitly, so the widget exists only once this module can
	// receive its token. Turnstile invokes the onload global when api.js is ready.
	var TURNSTILE_SCRIPT = 'https://challenges.cloudflare.com/turnstile/v0/api.js' +
		'?onload=articleRankingOnloadTurnstile&render=explicit';

	mw.ranking = {
		positiveVote: false,
		config: mw.config.get( 'wgArticleRankingConfig' ),
		$btns: $( '.ranking-section .sub-section1 .ranking-btn' ),
		$statusIcon: $( '<i>' ).addClass( 'fa fa-spinner fa-spin' ),
		$votingMessages: $( '.ranking-section .voting-messages' ),
		captcha: {
			container: null,
			widgetId: null,
			// Deferred waiting for the next token, if a vote or message asked for one
			pending: null,
			// A token is single-use: once one was issued, reset before the next
			issued: false,
			// The widget cannot be rendered, so no token will come
			unavailable: false
		},

		vote: function ( captchaToken ) {
			return new mw.Api().postWithToken( 'csrf', {
				action: 'rank-vote',
				pageid: mw.config.get( 'wgArticleId' ),
				captchaToken: captchaToken || null,
				vote: this.positiveVote ? 1 : -1
			} ).fail( function () {
				mw.ranking.informFailedVote();
			} ).done( function ( response ) {
				if ( response.success ) {
					mw.ranking.getClickedBtn().removeClass( 'on-call' ).addClass( 'after-success-call' );
					mw.ranking.setMessageSuccess( Number( mw.ranking.positiveVote ) );
					mw.ranking.trackEvent( 'vote', mw.ranking.positiveVote ? 'yes' : 'no' );
				} else {
					mw.ranking.informFailedVote();
				}
			} );
		},
		getClickedBtn: function () {
			return mw.ranking.$btns.filter( '.selected' );
		},
		setMessageSuccess: function ( voteType ) {
			$( '.ranking-section-wrapper' ).addClass( 'voted' ).addClass( voteType ? 'voted-positive' : 'voted-negative' );
			$( '.voting-messages' ).addClass( 'show' ).removeClass( 'voting-messages-wrp-failure' ).addClass( 'voting-messages-wrp-success' );
		},
		setMessageFailure: function () {
			$( '.voting-messages' ).addClass( 'show' ).removeClass( 'voting-messages-wrp-success' ).addClass( 'voting-messages-wrp-failure' );
		},
		resetButtons: function () {
			mw.ranking.$btns.attr( 'disabled', false ).removeClass( 'selected on-call' );
		},
		informFailedVote: function () {
			mw.ranking.resetButtons();
			mw.ranking.setMessageFailure();
		},

		/**
		 * Load Turnstile and render the widget into the ranking section.
		 */
		setupCaptcha: function () {
			var captcha = mw.ranking.captcha;
			captcha.container = document.querySelector( '.ranking-section .ranking-turnstile' );
			if ( !captcha.container ) {
				captcha.unavailable = true;
				return;
			}

			window.articleRankingOnloadTurnstile = mw.ranking.renderCaptcha;
			if ( window.turnstile ) {
				// Already loaded by another consumer of the shared widget
				mw.ranking.renderCaptcha();
			} else {
				mw.loader.getScript( TURNSTILE_SCRIPT ).fail( function () {
					captcha.unavailable = true;
					mw.ranking.onCaptchaError();
				} );
			}
		},
		renderCaptcha: function () {
			var captcha = mw.ranking.captcha;
			if ( captcha.widgetId !== null ) {
				return;
			}
			captcha.widgetId = window.turnstile.render( captcha.container, {
				sitekey: captcha.container.getAttribute( 'data-sitekey' ),
				// Identifies this service within the shared platform widget;
				// the server checks it.
				cData: 'articleranking-teen',
				language: captcha.container.getAttribute( 'data-language' ) || 'auto',
				// The challenge runs only when a vote or message asks for a token
				execution: 'execute',
				appearance: 'interaction-only',
				callback: mw.ranking.onCaptchaToken,
				'error-callback': mw.ranking.onCaptchaError,
				'timeout-callback': mw.ranking.onCaptchaError,
				// An issued token is spent on its request, so its expiry is no failure
				'expired-callback': function () {}
			} );
			if ( captcha.pending ) {
				// A token was asked for before the widget existed
				mw.ranking.executeCaptcha();
			}
		},
		executeCaptcha: function () {
			var captcha = mw.ranking.captcha;
			if ( captcha.issued ) {
				window.turnstile.reset( captcha.widgetId );
			}
			window.turnstile.execute( captcha.widgetId );
		},

		/**
		 * Get a fresh Turnstile token, for a vote or an after-vote message.
		 *
		 * @return {jQuery.Promise} Resolved with the token
		 */
		getCaptchaToken: function () {
			var captcha = mw.ranking.captcha;
			if ( captcha.unavailable ) {
				return $.Deferred().reject().promise();
			}
			captcha.pending = $.Deferred();
			if ( captcha.widgetId !== null ) {
				mw.ranking.executeCaptcha();
			}
			// Otherwise renderCaptcha() executes once the widget exists
			return captcha.pending.promise();
		},
		onCaptchaToken: function ( token ) {
			var pending = mw.ranking.captcha.pending;
			mw.ranking.captcha.issued = true;
			mw.ranking.captcha.pending = null;
			// A token nobody asked for (e.g. an automatic refresh) is dropped
			if ( pending ) {
				pending.resolve( token );
			}
		},
		onCaptchaError: function () {
			var pending = mw.ranking.captcha.pending;
			mw.ranking.captcha.pending = null;
			if ( pending ) {
				pending.reject();
			}
		},

		trackEvent: function ( action, label ) {
			if ( mw.ranking.config.trackClicks !== true ||
				mw.loader.getState( 'ext.googleUniversalAnalytics.utils' ) === null
			) {
				return;
			}

			mw.loader.using( 'ext.googleUniversalAnalytics.utils' ).then( function () {
				mw.googleAnalytics.utils.recordEvent( {
					eventCategory: 'article-ranking',
					eventAction: action,
					eventLabel: label,
					nonInteraction: false
				} );
			} );
		}
	};

	$( function () {
		$( mw.ranking.$btns ).on( 'click', function () {
			// In case we already displayed a message before. A class, not .hide():
			// an inline display would keep the next message hidden too.
			mw.ranking.$votingMessages.removeClass( 'show' );
			mw.ranking.positiveVote = $( this ).hasClass( 'yes' );
			mw.ranking.$btns.attr( 'disabled', true );
			// $( this ).prepend( mw.ranking.$statusIcon );
			$( this ).addClass( 'selected on-call' );
			if ( mw.ranking.config.isCaptchaEnabled === true ) {
				mw.ranking.getCaptchaToken().then( function ( token ) {
					mw.ranking.vote( token );
				}, mw.ranking.informFailedVote );
			} else {
				mw.ranking.vote();
			}
		} );

		if ( mw.ranking.config.isCaptchaEnabled === true ) {
			mw.ranking.setupCaptcha();
		}
	} );

}() );
