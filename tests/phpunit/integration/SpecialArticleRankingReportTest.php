<?php

namespace MediaWiki\Extension\ArticleRanking\Tests\Integration;

use MediaWiki\Extension\ArticleRanking\SpecialArticleRankingReport;
use MediaWiki\Request\FauxRequest;
use SpecialPageTestBase;

/**
 * @covers \MediaWiki\Extension\ArticleRanking\SpecialArticleRankingReport
 * @group ArticleRanking
 * @group Database
 */
class SpecialArticleRankingReportTest extends SpecialPageTestBase {

	/** @inheritDoc */
	protected function newSpecialPage() {
		return new SpecialArticleRankingReport();
	}

	/**
	 * @param int $pageId
	 * @param int $count Number of up-votes
	 * @param string|null $timestamp When they were cast; now if null
	 */
	private function insertVotes( int $pageId, int $count, ?string $timestamp = null ): void {
		$rows = [];
		for ( $i = 0; $i < $count; $i++ ) {
			$rows[] = [
				'ranking_page_id' => $pageId,
				'ranking_value' => 1,
				'ranking_timestamp' => $this->getDb()->timestamp( $timestamp ),
			];
		}
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'article_rankings2' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param string|null $subpage
	 * @param array $query
	 * @return string HTML
	 */
	private function runReport( ?string $subpage, array $query = [] ): string {
		[ $html ] = $this->executeSpecialPage(
			$subpage,
			new FauxRequest( $query ),
			'qqx',
			$this->getTestSysop()->getAuthority()
		);
		return $html;
	}

	/**
	 * The report's link to a page; the filter form echoes the target, so match the link itself
	 *
	 * @param string $titleText
	 * @return string
	 */
	private static function rowLink( string $titleText ): string {
		return '>' . htmlspecialchars( $titleText ) . '</a>';
	}

	public function testSubpageFiltersTheReportLikeTarget(): void {
		$wanted = $this->getExistingTestPage( 'ArticleRankingReportWanted' );
		$other = $this->getExistingTestPage( 'ArticleRankingReportOther' );
		$this->insertVotes( $wanted->getId(), 3 );
		$this->insertVotes( $other->getId(), 3 );
		$wantedLink = self::rowLink( $wanted->getTitle()->getPrefixedText() );
		$otherLink = self::rowLink( $other->getTitle()->getPrefixedText() );

		$unfiltered = $this->runReport( null, [ 'min_rankings' => 2 ] );
		$this->assertStringContainsString( $wantedLink, $unfiltered );
		$this->assertStringContainsString( $otherLink, $unfiltered );

		foreach ( [
			'subpage' => $this->runReport( $wanted->getTitle()->getPrefixedDBkey() ),
			'?target' => $this->runReport( null, [ 'target' => $wanted->getTitle()->getPrefixedText() ] ),
		] as $form => $html ) {
			$this->assertStringContainsString( $wantedLink, $html, $form );
			$this->assertStringNotContainsString( $otherLink, $html, $form );
		}

		// An explicit ?target wins over the subpage
		$html = $this->runReport(
			$wanted->getTitle()->getPrefixedDBkey(),
			[ 'target' => $other->getTitle()->getPrefixedText() ]
		);
		$this->assertStringContainsString( $otherLink, $html );
		$this->assertStringNotContainsString( $wantedLink, $html );
	}

	public function testDateRangeStaysInForceWhilePaging(): void {
		$recent = $this->getExistingTestPage( 'ArticleRankingReportRecent' );
		$old = $this->getExistingTestPage( 'ArticleRankingReportOld' );
		// The old page has the higher total, so it would lead an unfiltered report
		$this->insertVotes( $recent->getId(), 3, '20250601000000' );
		$this->insertVotes( $old->getId(), 5, '20200601000000' );
		$recentLink = self::rowLink( $recent->getTitle()->getPrefixedText() );
		$oldLink = self::rowLink( $old->getTitle()->getPrefixedText() );

		$range = [ 'start' => '2024-01-01', 'end' => '2026-12-31', 'min_rankings' => 2 ];
		// Paging forward (an offset above every total) and back must both keep the range
		foreach ( [
			'first page' => [],
			'offset' => [ 'offset' => '100' ],
			'dir=prev' => [ 'dir' => 'prev', 'offset' => '1' ],
		] as $step => $paging ) {
			$html = $this->runReport( null, $range + $paging );
			$this->assertStringContainsString( $recentLink, $html, $step );
			$this->assertStringNotContainsString( $oldLink, $html, $step );
		}
	}
}
