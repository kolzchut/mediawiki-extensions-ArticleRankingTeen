( function () {
	'use strict';

	function isElementInViewport( el ) {
		var rect = el.getBoundingClientRect();
		return ( rect.top > -1 && rect.bottom <= $( window ).height() );
	}
	// Not called at present: the resize handler below is empty.
	// eslint-disable-next-line no-unused-vars
	function scrollIfFocusedTextareaNotInFold() {
		var focusedTextarea = $( '.after-vote-form textarea:visible:focus' ),
			scrollTopOffset;
		if ( focusedTextarea.length && !isElementInViewport( focusedTextarea[ 0 ] ) ) {
			scrollTopOffset = focusedTextarea.offset().top -
				$( window ).height() +
				focusedTextarea.height() + 10;
			$( [ document.documentElement, document.body ] ).animate( {
				scrollTop: scrollTopOffset
			}, 800 );
		}
	}

	function openMessage( title ) {
		var messageDialog = new OO.ui.MessageDialog(),
			windowManager = new OO.ui.WindowManager();
		$( 'body' ).append( windowManager.$element );

		windowManager.addWindows( [ messageDialog ] );
		windowManager.openWindow( messageDialog, {
			title: title,
			actions: [
				{
					action: 'accept',
					label: mw.message( 'article-ranking-after-vote-OK' ).text(),
					flags: 'primary'
				}
			]
		} ).closed.done( function () {
			windowManager.$element.remove();
		} );
	}

	$( function () {
		$( '.after-vote-form textarea' ).on( 'keydown change', function () {
			if ( $( this ).val().trim() ) {
				$( '.after-voting-button' ).removeAttr( 'disabled' );
			} else {
				$( '.after-voting-button' ).attr( 'disabled', 'disabled' );
			}
		} ).trigger( 'keydown' );
		// catch virtual keyboard opening
		$( window ).on( 'resize', function () {

		} );
		$( '.after-voting-button' ).off( 'click' ).on( 'click', function () {
			var message = $( '.after-vote-form textarea:visible' ).val();

			function informFailedMessage() {
				$( '.after-voting-button' ).removeAttr( 'disabled' );
				openMessage( mw.message( 'article-ranking-after-vote-failed' ).text() );
			}

			function sendMessage( captchaToken ) {
				return new mw.Api().postWithToken( 'csrf', {
					action: 'rank-vote-message',
					id: mw.config.get( 'wgArticleId' ),
					captchaToken: captchaToken || null,
					message: message,
					vote: mw.ranking.positiveVote ? 1 : -1
				} ).fail( informFailedMessage ).done( function ( response ) {
					var $el;
					// The API answers success: 0 when it did not save the message
					if ( !( response && response.ranking && response.ranking.success ) ) {
						informFailedMessage();
						return;
					}
					$el = $( '<div>' )
						.addClass( 'vote-message-sent-text' )
						.html( mw.msg( 'article-ranking-after-vote-success' ) );
					$( '.after-vote-form' ).addClass( 'vote-message-sent' ).append( $el );
				} );
			}

			$( '.after-voting-button' ).attr( 'disabled', 'disabled' );
			if ( mw.ranking.config.isCaptchaEnabled === true ) {
				// A token is single-use, and the vote spent the last one
				return mw.ranking.getCaptchaToken().then( sendMessage, informFailedMessage );
			}
			return sendMessage();
		} );
	} );
}() );
