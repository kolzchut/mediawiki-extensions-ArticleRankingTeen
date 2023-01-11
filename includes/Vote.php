<?php

namespace MediaWiki\Extension\ArticleRanking;

use InvalidArgumentException;
use Hooks;
use MediaWiki\MediaWikiServices;
use RequestContext;
use TemplateParser;
use Title;

class Vote {

	/**
	 * Save vote for a certain page ID
	 *
	 * @param Title $title
	 * @param int $vote 1 for positive vote, 0 for negative vote
	 *
	 * @return bool
	 */
	public static function saveVote( Title $title, int $vote ) {
		if ( !in_array( $vote, [-1, 1] ) ) {
			throw new InvalidArgumentException( '$vote can only be -1 or 1' );
		}
		if ( !$title->exists() ) {
			throw new InvalidArgumentException( "$title does not exist" );
		}

		$requestContext = RequestContext::getMain();
		$dbw = wfGetDB( DB_PRIMARY );

		$result = $dbw->insert( 'article_rankings2', [
			'ranking_timestamp' => $dbw->timestamp(),
			'ranking_value' => $vote,
			'ranking_page_id' => $title->getArticleID(),
			'ranking_ip' => $requestContext->getRequest()->getIP(),
			'ranking_actor' => $requestContext->getUser()->getActorId()
		] );

		return (bool)$result;
	}

	/**
	 * Get rank for a specific page ID
	 *
	 * @param int $page_id
	 * @return array|bool an array that includes the number of positive votes, total votes and
	 *                    total rank percentage, or false
	 */
	public static function getRankingTotals( int $page_id ) {
		$dbr = wfGetDB( DB_REPLICA );

		$positiveVotes = $dbr->selectField(
			'article_rankings2',
			'SUM(ranking_value)',
			[
				'ranking_page_id' => $page_id,
				'ranking_value > 0'
			]
		);
		$negativeVotes = $dbr->selectField(
			'article_rankings2',
			'SUM(ranking_value)',
			[
				'ranking_page_id' => $page_id,
				'ranking_value' => -1
			]
		);

		// No results
		if ( $positiveVotes === false && $negativeVotes === false ) {
			return false;
		}

		$totalVotes = $positiveVotes + $negativeVotes;

		return [
			'positive_votes' => $positiveVotes,
			'negative_votes' => $negativeVotes,
			'total_votes'    => $totalVotes,
			'rank'           => ( $positiveVotes / $totalVotes ) * 100
		];
	}

	/**
	 * @see getRankingTotals()
	 */
	public static function getRank( int $page_id ) {
		return self::getRankingTotals( $page_id );
	}

	/**
	 * @param array $additionalParams
	 *
	 * @return string
	 * @throws \ConfigException
	 * @throws \FatalError
	 * @throws \MWException
	 */
	public static function createRankingSection( $additionalParams = [] ) {
		$conf = MediaWikiServices::getInstance()->getMainConfig();
		$wgArticleRankingCaptcha = $conf->get( 'ArticleRankingCaptcha' );
		$wgArticleRankingTemplateFileName = $conf->get( 'ArticleRankingTemplateFileName' );
		$wgArticleRankingTemplatePath = $conf->get( 'ArticleRankingTemplatePath' );
		$wgArticleRankingTemplatePath = $wgArticleRankingTemplatePath ? $wgArticleRankingTemplatePath : __DIR__ . '/templates';
		$templateParser = new TemplateParser( $wgArticleRankingTemplatePath );
		$params = [
			'section1title'  => self::getMsgForContent( 'ranking-section1-title' ),
			'yes'            => self::getMsgForContent( 'ranking-yes' ),
			'no'             => self::getMsgForContent( 'ranking-no' ),
			'section2title'  => self::getMsgForContent( 'ranking-section2-title' ),
			'ranking-vote-success'  => self::getMsgForContent( 'ranking-vote-success' ),
			'ranking-vote-fail'  => self::getMsgForContent( 'ranking-vote-fail' ),
			'proposeChanges' => self::getMsgForContent( 'ranking-propose-change' ),
			'voting-messages-positive-placeholder' => self::getMsgForContent( 'voting-messages-positive-placeholder' ),
			'voting-messages-negative-placeholder' => self::getMsgForContent( 'voting-messages-negative-placeholder' ),
			'is-captcha-enabled' => self::isCaptchaEnabled(),
			'is-after-vote-form' => $conf->get( 'ArticleRankingAddAfterVote' ),
			'after-voting-button' => self::getMsgForContent( 'after-vote-button' ) . '<i class="fas fa-chevron-left"></i>',
			'siteKey'        => $wgArticleRankingCaptcha[ 'siteKey' ]
		];
		$continue = Hooks::run( 'ArticleRankingTemplateParams', [ &$params , $additionalParams ] );
		if ( $continue ) {
			return $templateParser->processTemplate( $wgArticleRankingTemplateFileName, $params );
		}

		return '';
	}

	private static function getMsgForContent( $msgName ) {
		return wfMessage( $msgName )->inContentLanguage()->text();
	}

	/**
	 * Save vote message for a certain page ID
	 *
	 * @param int $page_id
	 * @param int $vote 1 for positive vote, 0 for negative vote
	 * @param string $message message for vote
	 * @return bool
	 */
	public static function saveVoteMessage( int $page_id, int $vote, string $message ) {
		$dbw = wfGetDB( DB_MASTER );
		$fields = [
				'positive_or_negative' => $vote,
				'votes_messages'    => $message,
				'votes_messages_page_id'        => $page_id,
				'votes_timestamp'        => $dbw->timestamp( wfTimestampNow() )
			];
		$result = $dbw->insert( 'article_rankings_votes_messages', $fields );
		return (bool)$result;
	}

	public static function isCaptchaEnabled() {
		global $wgArticleRankingCaptcha;
		return ( $wgArticleRankingCaptcha[ 'secret' ] && $wgArticleRankingCaptcha[ 'siteKey' ] );
	}
}
