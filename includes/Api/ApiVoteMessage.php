<?php
namespace MediaWiki\Extension\ArticleRanking\Api;

use ApiBase;
use MediaWiki\Extension\ArticleRanking\Captcha;
use MediaWiki\Extension\ArticleRanking\Vote;
use Wikimedia\ParamValidator\TypeDef\StringDef;
use MediaWiki\Title\Title;

class ApiVoteMessage extends ApiBase {

	/** Upper bound on a feedback message, in characters. */
	private const MAX_MESSAGE_CHARS = 2000;

	/**
	 * @return array
	 */
	protected function getAllowedParams() {
		return [
			'captchaToken' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => Vote::isCaptchaEnabled()
			],
			'id' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			],
			'message' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
				StringDef::PARAM_MAX_CHARS => self::MAX_MESSAGE_CHARS
			],
			'vote' => [
				ApiBase::PARAM_TYPE => [ '-1', '1' ],
				ApiBase::PARAM_REQUIRED => true
			]
		];
	}

	public function execute() {
		$queryResult = $this->getResult();
		$params      = $this->extractRequestParams();

		$captchaToken = $params['captchaToken'];
		$page_id = (int)$params[ 'id' ];
		$vote    = (int)$params[ 'vote' ];
		$message = $params[ 'message' ];
		$output  = [ 'success' => false ];

		// Origin-side abuse backstop (integrity, not volume). Edge-layer
		// volume/bot defense is tracked in kolzchut/kz-infrastructure#503.
		if ( $this->getUser()->pingLimiter( 'articleranking-vote' ) ) {
			$this->dieWithError( 'apierror-ratelimited', 'ratelimited' );
		}

		// Message length is capped by StringDef::PARAM_MAX_CHARS in
		// getAllowedParams(); the param validator rejects overlong input.

		// Only accept feedback for a page that actually exists.
		$title = Title::newFromID( $page_id );
		if ( !$title || !$title->exists() ) {
			$this->dieWithError( [ 'apierror-nosuchpageid', $page_id ], 'nosuchpageid' );
		}

		if ( !Vote::isCaptchaEnabled() || Captcha::verifyToken( $captchaToken ) ) {
			$result = Vote::saveVoteMessage( $page_id, $vote, $message );
			$output[ 'success' ] = (int)$result;
		}

		$queryResult->addValue( null, 'ranking', $output );
	}

	public function needsToken() {
		return 'csrf';
	}

}
