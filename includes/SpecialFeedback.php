<?php
/**
 * Implements Special:ArticleRankingFeedback
 *
 * @file
 * @ingroup SpecialPage
 */

namespace MediaWiki\Extension\ArticleRanking;

use DerivativeContext;
use HTMLForm;
use SpecialPage;

/**
 * A special page that lists feedback received after page ranking vote
 *
 * @ingroup SpecialPage
 */
class SpecialFeedback extends SpecialPage {
	protected $target;

	public function __construct( $name = 'ArticleRankingFeedback', $restriction = 'viewfeedback' ) {
		parent::__construct( $name, $restriction );
	}

	/**
	 * Main execution point
	 *
	 * @param string $par Title fragment
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'article-ranking-special-feedback' ) );
		$out->addModuleStyles( [ 'mediawiki.special' ] );

		$request = $this->getRequest();
		$this->target = trim( $request->getVal( 'wpTarget', $par ) );

		# setup the pager
		$pager = $this->getPager();

		# Just show the list
		$fields = [
			'Target' => [
				'type' => 'title',
				'label-message' => 'article-ranking-feedbacklist-title',
				'tabindex' => '1',
				'size' => '45',
				'required' => false,
				'default' => $this->target,
			],
			'Limit' => [
				'type' => 'limitselect',
				'label-message' => 'table_pager_limit_label',
				'options' => $pager->getLimitSelectList(),
				'name' => 'limit',
				'default' => $pager->getLimit(),
			],
		];
		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $this->getPageTitle() ); // Remove subpage
		$form = HTMLForm::factory( 'ooui', $fields, $context );
		$form
			->setMethod( 'get' )
			->setFormIdentifier( 'feedbacklist' )
			->setWrapperLegendMsg( 'articlerankingfeedback' )
			->setSubmitTextMsg( 'article-ranking-feedbacklist-submit' )
			->prepareForm()
			->displayForm( false );

		$this->showList( $pager );
	}

	/**
	 * Setup a new FeedbackPager instance.
	 * @return FeedbackPager
	 */
	protected function getPager() {
		$conds = [];

		return new FeedbackPager( $this, $conds );
	}

	/**
	 * Show the list of feedback matching the actual filter.
	 *
	 * @param FeedbackPager $pager The FeedbackPager instance for this page
	 */
	protected function showList( FeedbackPager $pager ) {
		$out = $this->getOutput();

		if ( $pager->getNumRows() ) {
			$out->addParserOutputContent( $pager->getFullOutput() );
		} elseif ( $this->target ) {
			$out->addWikiMsg( 'article-ranking-feedbacklist-no-results' );
		} else {
			$out->addWikiMsg( 'article-ranking-feedbacklist-empty' );
		}
	}
}