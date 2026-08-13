<?php
namespace MediaWiki\Extension\EduSharing;

use Job;
use GenericParameterJob;
use MediaWiki\MediaWikiServices;

class EduSharingUsageCleanupJob extends Job implements GenericParameterJob {
	/**
	 * @param array $params Job parameters containing nodeId and usageId
	 */
	public function __construct( array $params ) {
		parent::__construct( 'eduSharingUsageCleanup', $params );
	}

	/** @inheritDoc */
	public function run() {
		$nodeId = (string)( $this->params['nodeId'] ?? '' );
		$usageId = (string)( $this->params['usageId'] ?? '' );
		if ( $nodeId === '' || $usageId === '' ) {
			$this->setLastError( 'Missing edu-sharing nodeId or usageId' );
			return false;
		}

		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'edusharing' );
		$user = $services->getUserFactory()->newAnonymous();
		$eduService = new EduSharingService( $user, $config );
		if ( !$eduService->isAvailable ) {
			$this->setLastError(
				$eduService->availabilityError ?: 'edu-sharing repository unavailable'
			);
			return false;
		}

		try {
			$postData = new \stdClass();
			$postData->nodeId = $nodeId;
			$postData->usageId = $usageId;
			$eduService->deleteUsage( $postData );
			return true;
		} catch ( \Throwable $e ) {
			$this->setLastError( $e->getMessage() );
			return false;
		}
	}
}
