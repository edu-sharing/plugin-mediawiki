<?php


require_once dirname(__FILE__).'/EduSharingWebService.php';
require_once dirname(__FILE__).'/EsApplication.php';
require_once dirname(__FILE__).'/EsApplications.php';


class ESApp {

	private $basename;
	private $Conf;

	public function __construct() {
	}


  public function loadApps(){

     $_cnf_path = dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'conf'.DIRECTORY_SEPARATOR;
     $n = new EsApplications($_cnf_path.'ccapp-registry.properties.xml');
     $li = $n->getFileList();
     foreach ($li as $key => $val){
         $n1 = new EsApplication($_cnf_path.$val);
         $n1->readProperties();
         $this->Conf[$val]= $n1;
     	}
  	return $this->Conf;
  	}


	public function getAppByID($app_id) {

		if (isset($this->Conf['app-'.$app_id.'.properties.xml']))
		{
   		return $this->Conf['app-'.$app_id.'.properties.xml'];
		}

		return false;

	}

	public function getHomeConf() {

		if (isset($this->Conf['homeApplication.properties.xml']))
		{
   		return $this->Conf['homeApplication.properties.xml'];
		}

		return false;

	}


	public function getRemoteAppData($session,$app_id) {

		try {

    $hc         = $this->getHomeConf();
    $remote_app = $this->getAppByID($app_id);

      $client = new SoapClient($remote_app->prop_array['edu-webservice']);

			$params = array("applicationId" => $hc->prop_array['appid'],
											"username" => '',
											"email" => '',
											"ticket" => $session,
											"createUser" => false);

			$return = $client->authenticateByApp($params);

			return $return;


		} catch (Exception $e) {
		    error_log('Error getRemoteAppData in ' . get_class($this));
			return;
		}
	} 


	public function GetTicketByUser($username,$useremail) {

    $hc   = $this->getHomeConf();
		$cUrl  = $hc->prop_array['edu-webservice'].$hc->prop_array['edu-authentication_wsdl'];

		try {
            $eduservice =  new edusharingWebService($cUrl, array());
            
            $paramsTrusted = array("applicationId" => $hc->prop_array['appid'], "ticket" => RequestContext::getMain()->getRequest()->getSession()->getId(), "ssoData" => array(array('key' => 'eppn','value' => $username)));
            $alfReturn = $eduservice -> authenticateByTrustedApp($paramsTrusted);
            $ticket = $alfReturn -> authenticateByTrustedAppReturn -> ticket;   
            
			return $ticket;

		} catch (Exception $e) {
		    error_log('Error getting ticket in ' . get_class($this));
			return;
		}
	} // eof GetTicketByUser



	// --- get some nice text out of alfrescos error exceptions ---
	public function beautifyException($exception) {

		// still crap ... alf exceptions are not consistent/unified/defined yet :(
		switch (1) {
			case (isSet($exception->faultstring)):
				$_exception = $exception->faultstring;
				break;
			case (isset($exception->detail->{$exception->detail->exceptionName})):
				$_exception =$exception->detail->{$exception->detail->exceptionName};
				break;
			default:
				$_exception = "unknown";
		}



    switch(1) {
	    case (strpos($_exception, "SENDACTIVATIONLINK_SUCCESS") !== false):
	    	return get_string('exc_SENDACTIVATIONLINK_SUCCESS','campuscontent');
	    case (strpos($_exception, "APPLICATIONACCESS_NOT_ACTIVATED_BY_USER") !== false):
	    	return get_string('exc_APPLICATIONACCESS_NOT_ACTIVATED_BY_USER','campuscontent');
	    case (strpos($_exception, "Could not connect to host") !== false):
	    	return get_string('exc_COULD_NOT_CONNECT_TO_HOST','campuscontent');
			default:
				return get_string('exc_UNKNOWN_ERROR','campuscontent')."(".$_exception."<hr><pre>".var_dump($_exception)."</pre>)";
    }

	} // eof beautifyException


    public function edusharing_encrypt_with_repo_public($data) {
        $conf = $this->getHomeConf();
        $dataEncrypted = '';
        $repoPublicKey = openssl_get_publickey($conf->prop_array ['repo_public_key']);
        $encryption_status = openssl_public_encrypt($data ,$dataEncrypted, $repoPublicKey);
        if($encryption_status === false || $dataEncrypted === false) {
            error_log('Encryption error');
            exit();
        }
        return $dataEncrypted;
    }


}//eof class CCWebServiceFactory


?>