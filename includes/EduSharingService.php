<?php

require_once "edu-sharing-api/edu-sharing-helper.php";
require_once "edu-sharing-api/edu-sharing-helper-base.php";
require_once "edu-sharing-api/edu-sharing-auth-helper.php";
require_once "edu-sharing-api/edu-sharing-node-helper.php";

class EduSharingService {

    public $config;
    public $helperBase;

    public function __construct() {
        global $wgUser;
        $config = new EduSharingConfig($wgUser);
        $this -> config = $config;
        $this -> helperBase = new EduSharingHelperBase($config->baseUrl, $config->privateKey, $config->appId);        
    }

   
    // --- ---
    public function getAuthentication() {

        $cUrl = $this -> getEduWebservice();
        $cPath = $this -> getAuthentication_wsdl();

        return new edusharingWebService($cUrl . $cPath, array());
    }// eof getCCAuthentication

    public function getUsage() {

        $cUrl = $this -> getEduWebservice();
        $cPath = $this -> getUsage_wsdl();

        return new edusharingWebService($cUrl . $cPath, array());
    }// eof getCCAuthentication

    public function getAlfrescoService($ticket) {

        $cUrl = $this -> getEduWebservice();
        $cPath = $this -> getAlfresco_wsdl();

        return new edusharingWebService($cUrl . $cPath, array(), $ticket);
    }// eof getCCAuthentication

    public function createUsage( $postData)  {

        $nodeHelper = new EduSharingNodeHelper( $this->helperBase );
        $result = $nodeHelper->createUsage(
            $postData->ticket,
            $postData->containerId,
            $postData->resourceId,
            $postData->nodeId
        );
        return $result;

    }

    public function delUsage($params) {

    }

    public function getNode($postData) {
        $nodeHelper = new EduSharingNodeHelper($this->helperBase);
        $result = $nodeHelper->getNodeByUsage(
            new Usage(
                $postData->nodeId,
                $postData->nodeVersion,
                $postData->containerId,
                $postData->resourceId,
                $postData->usageId
            )
        );    
        return $result;
    }

    public function getTicket() {
        $authHelper = new EduSharingAuthHelper($this->helperBase);
        $ticket = '';
        try {
            $ticket = $authHelper->getTicketForUser($this->config->username);
        } catch (Exception $e) {
            error_log( "Couldn't get ticket from Edusharing repository ($e)" );
        }
        return $ticket;
    }

    public function encryptWithRepoKey( $data ) {
        
        $dataEncrypted = '';

        $repoPublicKey      = openssl_get_publickey( $this->config->repoPublicKey );
        $encryption_status  = openssl_public_encrypt( $data ,$dataEncrypted, $repoPublicKey );
        
        if( $encryption_status === false || $dataEncrypted === false ) {
            error_log('Encryption error');
            exit();
        }
        return $dataEncrypted;
    }

}
?>
