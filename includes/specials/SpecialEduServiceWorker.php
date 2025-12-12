<?php
namespace MediaWiki\Extension\EduSharing;

use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;

class SpecialEduServiceWorker extends SpecialPage {

	public function __construct() {
		parent::__construct( 'EduServiceWorker', '', false );
	}

	public function execute( $par ) {
		$this->getOutput()->disable();

		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'edusharing' );
		$eduService = new EduSharingService( $this->getUser(), $config );

		header( 'Content-Type: text/javascript' );
		header( 'Service-Worker-Allowed: /' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		$serviceWorkerUrl = rtrim( $eduService->config->baseUrl, '/' )
			. '/web-components/rendering-service/edu-service-worker.js';
		$content = @file_get_contents( $serviceWorkerUrl );

		if ( $content === false ) {
			echo '// Failed to load edu-sharing service worker';
			return;
		}

		echo $content;
	}
}
