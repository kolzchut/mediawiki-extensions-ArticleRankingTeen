<?php

namespace MediaWiki\Extension\ArticleRanking\Api;

use ApiBase;
use MediaWiki\Extension\ArticleRanking\Vote;

class ApiGetVotes extends ApiBase {

	/**
	 * @param \ApiMain $main
	 * @param string $moduleName
	 */
	public function __construct( $main, $moduleName ) {
		parent::__construct( $main, $moduleName );
	}

	/** @inheritDoc */
	protected function getAllowedParams() {
		return [
			'pageid' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			]
		];
	}

	public function execute() {
		$queryResult = $this->getResult();
		$params = $this->extractRequestParams();

		$page = $this->getTitleOrPageId( $params );
		// A page with no votes gets all-zero totals (total_votes = 0), as it always has
		$output = [
			'success' => 1,
			'votes' => Vote::getRank( $page->getId() ),
		];

		$queryResult->addValue( null, 'ranking', $output );
	}

}
