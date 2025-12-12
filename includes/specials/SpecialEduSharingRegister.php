<?php
namespace MediaWiki\Extension\EduSharing;

use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use SimpleXMLElement;

class SpecialEduSharingRegister extends SpecialPage {

    function __construct() {
		parent::__construct( 'EduSharingRegister', '', false );
	}

	public function execute( $par ) {
        $services = MediaWikiServices::getInstance();
        $mwConfig = $services->getConfigFactory()->makeConfig( 'edusharing' );

        $eduService = new EduSharingService( $this->getUser(), $mwConfig );
        $response = $this->getRequest()->response();

		$data = [
				'appid' => $eduService->config->appId,
				'public_key' => $eduService->config->getPublicKey(),
				'type' => $eduService->config->appType,
                'domain' => $eduService->config->appDomain,
                'host' => $eduService->config->appHost,
                'trustedclient' => 'true'
		];

		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>'
		.'<!DOCTYPE properties SYSTEM "http://java.sun.com/dtd/properties.dtd">'
		.'<properties></properties>');

		foreach ( $data as $key => $val ) {
			$xml->addChild( 'entry', $val )->addAttribute('key', $key);
		}

        // take over output since we dont't want any stuff around our xml
        $this->getOutput()->disable();
        $response->header( 'Content-Type: application/xml; charset=UTF-8' );
        $response->header( 'Cache-Control: no-store, must-revalidate' );
        echo $xml->asXML();

	}
}
