<?php
class SpecialEduSharingRegister extends SpecialPage {

    function __construct() {
		parent::__construct( 'EduSharingRegister', '', false );
	}

	private static function toXml(SimpleXMLElement $object, array $data) {   
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				$new_object = $object->addChild($key);
				self::toXml($new_object, $value);
			} else {
				// if the key is an integer, it needs text with it to actually work.
				if ($key != 0 && $key == (int) $key) {
					$key = "key_$key";
				}
	
				$object->addChild($key, $value);
			}   
		}   
	} 

	public function execute( $par ) {
        $config = new EduSharingConfig( $this->getUser() );

		$data = [
				'appid' => $config->appId,
				'public_key' => $config->getPublicKey(),
				'type' => $config->appType,
                'domain' => $config->appDomain,
                'host' => $config->appHost,
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

        print $xml->asXML();

	}
}
