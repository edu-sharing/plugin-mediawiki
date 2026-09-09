<?php
namespace MediaWiki\Extension\EduSharing;

use MediaWiki\Api\ApiBase;
use MediaWiki\MediaWikiServices;

/**
 * Returns a freshly minted repository picker URL for the current user.
 *
 * The ticket used to be embedded into the HTML of every page rendered in an
 * editing context. That failed whenever VisualEditor was activated client-side
 * (no page render, so no ticket at all) and went stale whenever an editor tab
 * stayed open longer than the ticket's lifetime. Fetching it here instead means
 * the ticket is obtained at the moment the user asks for the picker.
 *
 * @ingroup API
 */
class ApiEduSharingTicket extends ApiBase {

	/** @inheritDoc */
	public function execute() {
		// Anyone who can open the editor could already read the ticket from the
		// page source, so gate on the same capability rather than something new.
		$this->checkUserRightsAny( 'edit' );

		$services = MediaWikiServices::getInstance();
		$mwConfig = $services->getConfigFactory()->makeConfig( 'edusharing' );
		$service  = new EduSharingService( $this->getUser(), $mwConfig );

		if ( !$service->isAvailable ) {
			$this->dieWithError(
				[ 'apierror-edusharing-unavailable',
					wfEscapeWikiText( $service->availabilityError ?? '' ) ],
				'edusharing-unavailable'
			);
		}

		$ticket = $service->getTicket();
		if ( $ticket === null ) {
			// Most commonly the app is not (or no longer) registered at the
			// repository, or the configured repository user does not exist.
			$this->dieWithError( 'apierror-edusharing-noticket', 'edusharing-noticket' );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [
			'gui' => $service->config->baseUrl
				. '/components/search?ticket=' . rawurlencode( $ticket )
				. '&reurl=WINDOW',
		] );
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [];
	}

	/**
	 * The ticket is user specific and short lived, so it must never be cached.
	 *
	 * @inheritDoc
	 */
	public function getCacheMode( $params ) {
		return 'private';
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			'action=edusharingticket' => 'apihelp-edusharingticket-example-1',
		];
	}
}
