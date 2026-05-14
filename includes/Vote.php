<?php

namespace MediaWiki\Extension\ArticleRanking;

use InvalidArgumentException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\TemplateParser;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class Vote {

	/**
	 * Save a vote (+1 or -1) for the given page.
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

		$ctx = RequestContext::getMain();
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$dbw->newInsertQueryBuilder()
			->insertInto( 'article_rankings2' )
			->row( [
				'ranking_timestamp' => $dbw->timestamp(),
				'ranking_value'     => $vote,
				'ranking_page_id'   => $title->getArticleID(),
				'ranking_ip'        => $ctx->getRequest()->getIP(),
				'ranking_actor'     => $ctx->getUser()->getActorId(),
			] )
			->caller( __METHOD__ )
			->execute();

		return true;
	}

	/**
	 * Get vote totals for a page.
	 *
	 * @return array{positive_votes:int,negative_votes:int,total_votes:int,rank:float}|false
	 */
	public static function getRankingTotals( int $page_id ) {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

		$positiveVotes = $dbr->newSelectQueryBuilder()
			->select( 'SUM(ranking_value)' )
			->from( 'article_rankings2' )
			->where( [ 'ranking_page_id' => $page_id, 'ranking_value > 0' ] )
			->caller( __METHOD__ )
			->fetchField();
		$negativeVotes = $dbr->newSelectQueryBuilder()
			->select( 'SUM(ranking_value)' )
			->from( 'article_rankings2' )
			->where( [ 'ranking_page_id' => $page_id, 'ranking_value' => -1 ] )
			->caller( __METHOD__ )
			->fetchField();

		if ( $positiveVotes === false && $negativeVotes === false ) {
			return false;
		}

		$totalVotes = (int)$positiveVotes + (int)$negativeVotes;

		return [
			'positive_votes' => (int)$positiveVotes,
			'negative_votes' => (int)$negativeVotes,
			'total_votes'    => $totalVotes,
			'rank'           => $totalVotes !== 0 ? ( (int)$positiveVotes / $totalVotes ) * 100 : 0.0,
		];
	}

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
