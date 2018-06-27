<?php

class ArticleRankingHooks {

	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		global $wgArticleRankingCaptcha;

		if ( !$wgArticleRankingCaptcha[ 'secret' ] || !$wgArticleRankingCaptcha[ 'siteKey' ] ) {
			$out->showErrorPage( 'ranking-invalid-captcha-title', 'ranking-invalid-captcha-keys-message' );
		}

		$out->addModules( [ 'ext.articleRanking', 'ext.articleRanking.changeRequest' ] );
		$out->addHeadItem(
			'recaptcha', '<script async defer src="https://www.google.com/recaptcha/api.js"></script>'
		);

		return true;
	}

	/**
	 * Hook: ResourceLoaderGetConfigVars called right before
	 * ResourceLoaderStartUpModule::getConfig returns
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderGetConfigVars
	 *
	 * @param &$vars array of variables to be added into the output of the startup module.
	 *
	 * @return true
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgArticleRankingConfig;
		$vars['wgArticleRankingConfig'] = $wgArticleRankingConfig;

		return true;
	}

}
