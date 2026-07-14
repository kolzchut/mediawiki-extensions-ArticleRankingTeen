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
		$dir = __DIR__ . '/../sql';

		$updater->addExtensionTable(
			'article_rankings2',
			"$dir/ArticleRankingsNewTableFormat.2022-03-29.sql"
		);
		$updater->dropExtensionTable(
			'article_rankings',
			"$dir/ArticleRankingMigrateDataFromOldTable.2022-04-12.sql"
		);
		$updater->addExtensionTable(
			'article_rankings_votes_messages',
			"$dir/ArticleRankingsVoteMessages.sql"
		);

		// Per-identity dedup: add the voter key, then the unique index that
		// enforces one live vote per (page, identity). See ArticleRanking#8.
		// On fresh installs the base table already carries both, so these are
		// no-ops; they only fire on existing installs.
		$updater->addExtensionField(
			'article_rankings2',
			'ranking_voter_key',
			"$dir/patch-add-voter-key.sql"
		);
		$updater->addExtensionIndex(
			'article_rankings2',
			'ranking_voter_dedup',
			"$dir/patch-add-voter-dedup-index.sql"
		);
	}
}
