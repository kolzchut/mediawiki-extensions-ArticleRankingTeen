<?php

namespace MediaWiki\Extension\ArticleRanking;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

/**
 * Installer-time hook handlers. Kept separate from {@see Hooks} because
 * LoadExtensionSchemaUpdates fires before the service container is
 * wired, and HookContainer rejects handlers declaring `services:` for
 * such hooks since ~MW 1.41.
 */
class InstallerHooks implements LoadExtensionSchemaUpdatesHook {
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable(
			'article_rankings2',
			__DIR__ . '/../sql/ArticleRankingsNewTableFormat.2022-03-29.sql'
		);
		$updater->dropExtensionTable(
			'article_rankings',
			__DIR__ . '/../sql/ArticleRankingMigrateDataFromOldTable.2022-04-12.sql'
		);
		$updater->addExtensionTable(
			'article_rankings_votes_messages',
			__DIR__ . '/../sql/ArticleRankingsVoteMessages.sql'
		);
	}
}
