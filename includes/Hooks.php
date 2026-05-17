<?php

namespace MediaWiki\Extension\ArticleRanking;

use MediaWiki\Config\Config;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;

class Hooks implements
	BeforePageDisplayHook,
	ResourceLoaderGetConfigVarsHook
{
	public function __construct(
		private readonly Config $config,
	) {
	}

	public function onBeforePageDisplay( $out, $skin ): void {
		$modules = [ 'ext.articleRanking' ];

		if ( $this->config->get( 'ArticleRankingAddChangeRequest' ) ) {
			$modules[] = 'ext.articleRanking.changeRequest';
		}
		if ( $this->config->get( 'ArticleRankingAddAfterVote' ) ) {
			$modules[] = 'ext.articleRanking-after-vote';
		}

		$out->addModules( $modules );

		if ( Captcha::isEnabled() ) {
			$out->addHeadItem( 'captcha', Captcha::getScript() );
		}
	}

	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['wgArticleRankingConfig'] = $this->config->get( 'ArticleRankingConfig' );
		$vars['wgArticleRankingConfig']['isCaptchaEnabled'] = Captcha::isEnabled();
	}

}
