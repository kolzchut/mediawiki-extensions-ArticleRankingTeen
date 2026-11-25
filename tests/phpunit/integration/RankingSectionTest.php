<?php

namespace MediaWiki\Extension\ArticleRanking\Tests\Integration;

use MediaWiki\Extension\ArticleRanking\Vote;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\ArticleRanking\Vote::createRankingSection
 * @group ArticleRanking
 */
class RankingSectionTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Keep a skin's param hook out of it: only the template lookup is under test
		$this->clearHook( 'ArticleRankingTemplateParams' );
		$this->overrideConfigValues( [
			'ArticleRankingTemplateFileName' => 'voting',
			'ArticleRankingCaptcha' => [ 'siteKey' => '', 'secret' => '' ],
		] );
	}

	public function testRendersBundledTemplateWhenNoPathIsConfigured(): void {
		$this->overrideConfigValue( 'ArticleRankingTemplatePath', false );

		$html = Vote::createRankingSection();

		$this->assertStringContainsString( 'class="ranking-section-wrapper"', $html );
		$this->assertStringContainsString( 'ranking-btn yes', $html );
	}

	public static function provideAfterVoteForm(): array {
		return [
			'after-vote form on' => [ true, false ],
			'after-vote form off' => [ false, true ],
		];
	}

	/**
	 * @dataProvider provideAfterVoteForm
	 */
	public function testBundledTemplateAlwaysHasTheFailureMessage(
		bool $afterVoteForm, bool $hasSuccessMessage
	): void {
		$this->overrideConfigValues( [
			'ArticleRankingTemplatePath' => false,
			'ArticleRankingAddAfterVote' => $afterVoteForm,
		] );

		$html = Vote::createRankingSection();

		$this->assertStringContainsString( 'class="voting-messages-failure"', $html );
		$this->assertSame( $afterVoteForm, str_contains( $html, 'class="after-vote-form"' ) );
		// With the form on, the form takes the success message's place
		$this->assertSame( $hasSuccessMessage, str_contains( $html, 'class="voting-messages-success"' ) );
	}

	public function testConfiguredPathWinsOverTheBundledTemplate(): void {
		$dir = $this->getNewTempDirectory();
		file_put_contents( "$dir/voting.mustache", '<p class="custom-voting">{{ yes }}</p>' );
		$this->overrideConfigValue( 'ArticleRankingTemplatePath', $dir );

		$html = Vote::createRankingSection();

		$this->assertStringContainsString( 'class="custom-voting"', $html );
		$this->assertStringNotContainsString( 'ranking-section-wrapper', $html );
	}
}
