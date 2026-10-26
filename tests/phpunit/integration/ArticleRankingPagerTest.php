<?php

namespace MediaWiki\Extension\ArticleRanking\Tests\Integration;

use MediaWiki\Extension\ArticleRanking\ArticleRankingPager;
use MediaWiki\Extension\ArticleRanking\SpecialArticleRankingReport;
use MediaWikiIntegrationTestCase;
use RequestContext;

/**
 * @covers \MediaWiki\Extension\ArticleRanking\ArticleRankingPager
 * @group ArticleRanking
 * @group Database
 */
class ArticleRankingPagerTest extends MediaWikiIntegrationTestCase {

	private function newPager( string $target ): ArticleRankingPager {
		$special = new SpecialArticleRankingReport();
		$special->setContext( RequestContext::getMain() );

		return new ArticleRankingPager(
			$special,
			[ 'target' => $target, 'start' => null, 'end' => null, 'min_rankings' => 0 ],
			$this->getServiceContainer()->getLinkRenderer()
		);
	}

	public function testInvalidTargetMatchesNoPage(): void {
		// '<' is never legal in a title, so Title::newFromText() returns null
		$pager = $this->newPager( '<invalid>' );

		$this->assertSame( 0, $pager->mConds['ranking_page_id'] );
		$this->assertSame( 0, $pager->getNumRows() );
	}

	public function testVotesForAMissingPageRenderWithoutAFatal(): void {
		// Votes outlive their page: no page has this id
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'article_rankings2' )
			->row( [
				'ranking_page_id' => 987654,
				'ranking_value' => 1,
				'ranking_timestamp' => $this->getDb()->timestamp(),
			] )
			->caller( __METHOD__ )
			->execute();

		$pager = $this->newPager( '' );

		$this->assertSame( 1, $pager->getNumRows() );
		$this->assertStringContainsString( '<table', $pager->getBody() );
	}

	public function testExistingTargetFiltersByItsPageId(): void {
		$page = $this->getExistingTestPage( 'ArticleRankingPagerTest' );

		$pager = $this->newPager( $page->getTitle()->getPrefixedText() );

		$this->assertSame( $page->getId(), $pager->mConds['ranking_page_id'] );
	}
}
