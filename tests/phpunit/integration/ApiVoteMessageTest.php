<?php

namespace MediaWiki\Extension\ArticleRanking\Tests\Integration;

use ApiTestCase;
use ApiUsageException;

/**
 * @covers \MediaWiki\Extension\ArticleRanking\Api\ApiVoteMessage
 * @covers \MediaWiki\Extension\ArticleRanking\Vote::saveVoteMessage
 * @group ArticleRanking
 * @group API
 * @group Database
 */
class ApiVoteMessageTest extends ApiTestCase {

	protected function setUp(): void {
		parent::setUp();
		// The live configuration: no captcha, and no rate limit getting in the way
		$this->overrideConfigValue( 'ArticleRankingCaptcha', [ 'siteKey' => '', 'secret' => '' ] );
		$this->overrideConfigValue( 'RateLimits', [] );
	}

	/**
	 * @param string|int $vote
	 * @param int $pageId
	 * @return array API result
	 */
	private function sendMessage( $vote, int $pageId ): array {
		[ $result ] = $this->doApiRequestWithToken( [
			'action' => 'rank-vote-message',
			'id' => $pageId,
			'message' => 'Feedback text',
			'vote' => (string)$vote,
		] );
		return $result;
	}

	/**
	 * @param int $pageId
	 * @return array[] [ value, message ] for each stored row of that page
	 */
	private function storedMessages( int $pageId ): array {
		$rows = $this->getDb()->newSelectQueryBuilder()
			->select( [ 'positive_or_negative', 'votes_messages' ] )
			->from( 'article_rankings_votes_messages' )
			->where( [ 'votes_messages_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$stored = [];
		foreach ( $rows as $row ) {
			$stored[] = [ (int)$row->positive_or_negative, $row->votes_messages ];
		}
		return $stored;
	}

	public static function provideAcceptedVotes(): array {
		return [
			// Stored as the table has always stored them: 1 for yes, 0 for no
			'positive vote' => [ 1, 1 ],
			'negative vote' => [ -1, 0 ],
		];
	}

	/**
	 * @dataProvider provideAcceptedVotes
	 */
	public function testMessageIsAcceptedAndStored( int $vote, int $expectedStored ): void {
		$pageId = $this->getExistingTestPage()->getId();

		$result = $this->sendMessage( $vote, $pageId );

		$this->assertSame( 1, $result['ranking']['success'] );
		$this->assertSame( [ [ $expectedStored, 'Feedback text' ] ], $this->storedMessages( $pageId ) );
	}

	public static function provideRefusedVotes(): array {
		return [
			// What the form sent after a negative vote before it sent -1
			'zero' => [ '0' ],
			'two' => [ '2' ],
			'not a number' => [ 'yes' ],
		];
	}

	/**
	 * @dataProvider provideRefusedVotes
	 */
	public function testOtherVoteValuesAreRefused( string $vote ): void {
		$pageId = $this->getExistingTestPage()->getId();

		try {
			$this->sendMessage( $vote, $pageId );
			$this->fail( 'Expected the API to refuse vote=' . $vote );
		} catch ( ApiUsageException $e ) {
			$this->assertApiErrorCode( 'badvalue', $e );
		}
		$this->assertSame( [], $this->storedMessages( $pageId ) );
	}
}
