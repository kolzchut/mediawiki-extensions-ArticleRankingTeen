<?php

namespace MediaWiki\Extension\ArticleRanking\Tests\Integration;

use MediaWiki\Extension\ArticleRanking\Vote;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\ArticleRanking\Vote::getRankingTotals
 * @group ArticleRanking
 * @group Database
 */
class VoteTest extends MediaWikiIntegrationTestCase {

	/**
	 * @param int $pageId
	 * @param int[] $values
	 */
	private function insertVotes( int $pageId, array $values ): void {
		$rows = [];
		foreach ( $values as $value ) {
			// No voter key, like pre-dedup rows: NULL keys never collide in the dedup index
			$rows[] = [
				'ranking_page_id' => $pageId,
				'ranking_value' => $value,
				'ranking_timestamp' => $this->getDb()->timestamp(),
			];
		}
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'article_rankings2' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	public function testPageWithNoVotesYieldsZeros(): void {
		$this->assertSame(
			[
				'positive_votes' => 0,
				'negative_votes' => 0,
				'total_votes' => 0,
				'rank' => 0.0,
			],
			Vote::getRankingTotals( 999999 )
		);
	}

	public function testUpVotesOnly(): void {
		$this->insertVotes( 4141, [ 1, 1, 1 ] );

		$totals = Vote::getRankingTotals( 4141 );

		$this->assertSame( 3, $totals['positive_votes'] );
		$this->assertSame( 0, $totals['negative_votes'] );
		$this->assertSame( 3, $totals['total_votes'] );
		$this->assertEqualsWithDelta( 100.0, $totals['rank'], 0.0001 );
	}

	public function testDownVotesOnly(): void {
		$this->insertVotes( 4040, [ -1, -1 ] );

		$totals = Vote::getRankingTotals( 4040 );

		$this->assertSame( 0, $totals['positive_votes'] );
		$this->assertSame( 2, $totals['negative_votes'] );
		$this->assertSame( 2, $totals['total_votes'] );
		$this->assertEqualsWithDelta( 0.0, $totals['rank'], 0.0001 );
	}

	public function testCountsIncludeMigratedTallies(): void {
		// Single votes plus one migrated v1 tally of each sign: 25 up, 2 down in all
		$this->insertVotes( 4242, [ 1, 1, 23, -1, -1 ] );
		// Another page's votes must not leak in
		$this->insertVotes( 4343, [ -5, 1 ] );

		$totals = Vote::getRankingTotals( 4242 );

		$this->assertSame( 25, $totals['positive_votes'] );
		$this->assertSame( 2, $totals['negative_votes'] );
		$this->assertSame( 27, $totals['total_votes'] );
		$this->assertEqualsWithDelta( 2500 / 27, $totals['rank'], 0.0001 );
	}

	public function testMigratedNegativeTallyIsCounted(): void {
		$this->insertVotes( 4444, [ 6, -11 ] );

		$totals = Vote::getRankingTotals( 4444 );

		$this->assertSame( 6, $totals['positive_votes'] );
		$this->assertSame( 11, $totals['negative_votes'] );
		$this->assertSame( 17, $totals['total_votes'] );
		$this->assertEqualsWithDelta( 600 / 17, $totals['rank'], 0.0001 );
	}
}
