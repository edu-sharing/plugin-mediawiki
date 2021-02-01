<?php


/**
 * Extension of PHP SoapClient
 *
 * Mainly injects authentification stuff to some parent methods.
 *
 */
class edusharingWebService extends SoapClient {
	private $appProperties;
	
	public function __construct($wsdl, $options = array()) {
		$this -> mod_edusharing_set_app_properties();
		parent::__construct($wsdl, $options);
		$this -> mod_edusharing_set_soap_headers();
	}
	
	private function mod_edusharing_set_soap_headers() {
		try {
			$timestamp = round(microtime(true) * 1000);
			$signData = $this -> mod_edusharing_get_app_properties()['appid'] . $timestamp;
			$priv_key = $this -> mod_edusharing_get_app_properties()['private_key'];
			$pkeyid = openssl_get_privatekey($priv_key);
			openssl_sign($signData, $signature, $pkeyid);
			$signature = base64_encode($signature);
			openssl_free_key($pkeyid);
			$headers = array();
			$headers[] = new SOAPHeader('http://webservices.edu_sharing.org', 'appId', $this -> mod_edusharing_get_app_properties()['appid']);
			$headers[] = new SOAPHeader('http://webservices.edu_sharing.org', 'timestamp', $timestamp);
			$headers[] = new SOAPHeader('http://webservices.edu_sharing.org', 'signature', $signature);
			$headers[] = new SOAPHeader('http://webservices.edu_sharing.org', 'signed', $signData);
			parent::__setSoapHeaders($headers);
		} catch (Exception $e) {
			throw new Exception('Could not set soap headers - ' . $e -> getMessage());
		}
	}
	
	public function mod_edusharing_set_app_properties() {
		
		$es = new ESApp();
		$es -> loadApps();
		$conf = $es -> getHomeConf();
		$this -> appProperties = $conf -> prop_array;
	}
	
	public function mod_edusharing_get_app_properties() {
		if(empty($this -> appProperties))
			throw new Exception('No appProperties found');
			return $this -> appProperties;
	}
	
}
?>
