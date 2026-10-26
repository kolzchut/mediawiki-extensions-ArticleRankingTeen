<?php

namespace MediaWiki\Extension\ArticleRanking;

use InvalidArgumentException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class Vote {

	/**
	 * Save (or update) the current voter's vote for a page.
	 *
	 * One live vote per identity per page: a repeat vote replaces the prior
	 * value (last-wins) rather than accumulating a new row, which is what let
	 * the endpoint be ballot-stuffed. See ArticleRanking#8.
	 *
	 * @return bool
	 */
	public static function saveVote( Title $title, int $vote ) {
		if ( !in_array( $vote, [ -1, 1 ], true ) ) {
			throw new InvalidArgumentException( '$vote can only be -1 or 1' );
		}
		if ( !$title->exists() ) {
			throw new InvalidArgumentException( "$title does not exist" );
		}

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$dbw->newInsertQueryBuilder()
			->insertInto( 'article_rankings2' )
			->row( [
				'ranking_page_id'   => $title->getArticleID(),
				'ranking_voter_key' => self::getVoterKey(),
				'ranking_value'     => $vote,
				'ranking_timestamp' => $dbw->timestamp(),
			] )
			->onDuplicateKeyUpdate()
			->uniqueIndexFields( [ 'ranking_page_id', 'ranking_voter_key' ] )
			->set( [
				'ranking_value'     => $vote,
				'ranking_timestamp' => $dbw->timestamp(),
			] )
			->caller( __METHOD__ )
			->execute();

		return true;
	}

	/**
	 * A stable, pseudonymous identifier for the current voter, used to enforce
	 * one live vote per identity per page.
	 *
	 * Registered users key by user id; anonymous users key by a keyed hash of
	 * their IP — the HMAC secret is $wgSecretKey, which lives outside this
	 * table, so a DB-only compromise cannot recover the address. Deliberately
	 * does NOT call getActorId(), to keep anonymous voting decoupled from
	 * MediaWiki's actor / temporary-account machinery.
	 *
	 * @return string
	 */
	private static function getVoterKey(): string {
		$ctx  = RequestContext::getMain();
		$user = $ctx->getUser();
		if ( $user->isRegistered() ) {
			return 'u:' . $user->getId();
		}
		$secret = MediaWikiServices::getInstance()->getMainConfig()->get( 'SecretKey' );
		return 'ip:' . hash_hmac( 'sha256', $ctx->getRequest()->getIP(), $secret );
	}

	/**
	 * Get vote totals for a page.
	 *
	 * @param int $page_id
	 * @return array{positive_votes:int,negative_votes:int,total_votes:int,rank:float}
	 *   See computeTotals(). A page with no votes yields all zeros.
	 */
	public static function getRankingTotals( int $page_id ) {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		// Sums rather than counts for b/c: rows migrated from the v1 table hold a whole
		// page's tally in a single row (see ArticleRankingPager::getQueryInfo()).
		// SUM() over no rows is NULL, which the int casts turn into 0.
		$positiveVotes = (int)$dbr->newSelectQueryBuilder()
			->select( 'SUM(ranking_value)' )
			->from( 'article_rankings2' )
			->where( [ 'ranking_page_id' => $page_id, 'ranking_value > 0' ] )
			->caller( __METHOD__ )
			->fetchField();
		// Down-votes are stored as negative values; negate them so this is a count
		$negativeVotes = (int)$dbr->newSelectQueryBuilder()
			->select( 'SUM(-ranking_value)' )
			->from( 'article_rankings2' )
			->where( [ 'ranking_page_id' => $page_id, 'ranking_value < 0' ] )
			->caller( __METHOD__ )
			->fetchField();

		return self::computeTotals( $positiveVotes, $negativeVotes );
	}

	/**
	 * Compute the ranking totals from the number of up-votes and down-votes.
	 *
	 * @param int $positiveVotes number of up-votes (>= 0)
	 * @param int $negativeVotes number of down-votes, as a positive count (>= 0)
	 * @return array{positive_votes:int,negative_votes:int,total_votes:int,rank:float}
	 *   rank is the percentage of up-votes, 0-100; 0 when there are no votes
	 */
	public static function computeTotals( int $positiveVotes, int $negativeVotes ): array {
		if ( $positiveVotes < 0 || $negativeVotes < 0 ) {
			throw new InvalidArgumentException( 'Vote counts cannot be negative' );
		}

		$totalVotes = $positiveVotes + $negativeVotes;

		return [
			'positive_votes' => $positiveVotes,
			'negative_votes' => $negativeVotes,
			'total_votes'    => $totalVotes,
			'rank'           => $totalVotes > 0 ? ( $positiveVotes / $totalVotes ) * 100.0 : 0.0,
		];
	}

	/**
	 * Alias of getRankingTotals().
	 *
	 * @param int $page_id
	 * @return array{positive_votes:int,negative_votes:int,total_votes:int,rank:float}
	 */
	public static function getRank( int $page_id ) {
		return self::getRankingTotals( $page_id );
	}

	/**
	 * Render the voting widget for an article. Called by KolzchutYoungSkin's
	 * voting-widget parser function.
	 *
	 * @return string HTML
	 */
	public static function createRankingSection( array $additionalParams = [] ): string {
		$services = MediaWikiServices::getInstance();
		$conf     = $services->getMainConfig();

		$captchaCfg     = $conf->get( 'ArticleRankingCaptcha' );
		$templateFile   = $conf->get( 'ArticleRankingTemplateFileName' );
		$templatePath   = $conf->get( 'ArticleRankingTemplatePath' ) ?: __DIR__ . '/templates';
		$templateParser = new TemplateParser( $templatePath );

		$params = [
			'section1title'                        => self::getMsgForContent( 'ranking-section1-title' ),
			'yes'                                  => self::getMsgForContent( 'ranking-yes' ),
			'no'                                   => self::getMsgForContent( 'ranking-no' ),
			'section2title'                        => self::getMsgForContent( 'ranking-section2-title' ),
			'ranking-vote-success'                 => self::getMsgForContent( 'ranking-vote-success' ),
			'ranking-vote-fail'                    => self::getMsgForContent( 'ranking-vote-fail' ),
			'proposeChanges'                       => self::getMsgForContent( 'ranking-propose-change' ),
			'voting-messages-positive-placeholder' => self::getMsgForContent( 'voting-messages-positive-placeholder' ),
			'voting-messages-negative-placeholder' => self::getMsgForContent( 'voting-messages-negative-placeholder' ),
			'is-captcha-enabled'                   => self::isCaptchaEnabled(),
			'is-after-vote-form'                   => $conf->get( 'ArticleRankingAddAfterVote' ),
			'after-voting-button'                  => self::getMsgForContent( 'after-vote-button' )
				. '<i class="fas fa-chevron-left"></i>',
			'siteKey'                              => $captchaCfg['siteKey'] ?? '',
			// Explicit widget language (he/ar) rather than browser auto-detect.
			'language'                             => RequestContext::getMain()->getLanguage()->getCode(),
		];

		$continue = $services->getHookContainer()->run(
			'ArticleRankingTemplateParams',
			[ &$params, $additionalParams ]
		);
		if ( $continue ) {
			return $templateParser->processTemplate( $templateFile, $params );
		}
		return '';
	}

	private static function getMsgForContent( string $msgName ): string {
		return wfMessage( $msgName )->inContentLanguage()->text();
	}

	/**
	 * Save a free-form vote message.
	 *
	 * @return bool
	 */
	public static function saveVoteMessage( int $page_id, int $vote, string $message ) {
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$dbw->newInsertQueryBuilder()
			->insertInto( 'article_rankings_votes_messages' )
			->row( [
				'positive_or_negative'   => $vote,
				'votes_messages'         => $message,
				'votes_messages_page_id' => $page_id,
				'votes_timestamp'        => $dbw->timestamp( wfTimestampNow() ),
			] )
			->caller( __METHOD__ )
			->execute();
		return true;
	}

	public static function isCaptchaEnabled(): bool {
		$cfg = MediaWikiServices::getInstance()->getMainConfig()->get( 'ArticleRankingCaptcha' );
		return !empty( $cfg['secret'] ) && !empty( $cfg['siteKey'] );
	}
}
