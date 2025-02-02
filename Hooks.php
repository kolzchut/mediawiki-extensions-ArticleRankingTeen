<?php
namespace MediaWiki\Extension\ArticleRanking;

use MediaWiki\MediaWikiServices;
use OutputPage;
use Skin;

class Hooks {

	/**
	 * @param OutputPage &$out
	 * @param Skin &$skin
	 *
	 * @return bool
	 * @throws \ConfigException
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$conf = MediaWikiServices::getInstance()->getMainConfig();
		$wgArticleRankingAddChangeRequest = $conf->get( 'ArticleRankingAddChangeRequest' );
		if ( $wgArticleRankingAddChangeRequest ) {
			$out->addModules( [ 'ext.articleRanking.changeRequest' ] );
		}
		$out->addModules( [ 'ext.articleRanking' ] );
		if ( $conf->get( 'ArticleRankingAddAfterVote' ) ) {
			$out->addModules( [ 'ext.articleRanking-after-vote' ] );

		}
		// $out->showErrorPage( 'ranking-invalid-captcha-title', 'ranking-invalid-captcha-keys-message' );
		if ( ArticleRanking::isCaptchaEnabled() ) {
			$out->addHeadItem(
				'recaptcha',
				'<script async defer src="https://www.google.com/recaptcha/api.js"></script>'
			);
		}

		return true;
	}

	/**
	 * Hook: ResourceLoaderGetConfigVars called right before
	 * ResourceLoaderStartUpModule::getConfig returns
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderGetConfigVars
	 *
	 * @param array &$vars variables to be added into the output of the startup module.
	 *
	 * @return true
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgArticleRankingConfig;
		$vars['wgArticleRankingConfig'] = $wgArticleRankingConfig;
		$vars['wgArticleRankingConfig']['isCaptchaEnabled'] = ArticleRanking::isCaptchaEnabled();

		return true;
	}

}
