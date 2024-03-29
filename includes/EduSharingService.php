<?php

require_once "EduSharingApiClient/edu-sharing-plugin/edu-sharing-helper.php";
require_once "EduSharingApiClient/edu-sharing-plugin/edu-sharing-helper-base.php";
require_once "EduSharingApiClient/edu-sharing-plugin/edu-sharing-auth-helper.php";
require_once "EduSharingApiClient/edu-sharing-plugin/edu-sharing-node-helper.php";

class EduSharingService {

    public $config;
    public $helperBase;
    private $authHelper;

    public function __construct() {
        global $wgUser;
        $config = new EduSharingConfig( $wgUser );
        $this -> config = $config;
        $this -> helperBase = new EduSharingHelperBase( $config->baseUrl, $config->privateKey, $config->appId );
        $this -> authHelper = new EduSharingAuthHelper( $this->helperBase ); 
    }

   
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

    public function deleteUsage( $postData ) {
        $nodeHelper = new EduSharingNodeHelper($this->helperBase);
        try {
            $result = $nodeHelper->deleteUsage(
                $postData->nodeId,
                $postData->usageId
            );    
            return $result;

        } catch ( Exception $e ) {
            if ( $e instanceof UsageDeletedException ) {
                error_log( 'noted, deleting locally: ' . $e->getMessage() ); 
            } else {
                throw $e;
            }            
        }
    }

    public function getNode($postData) {
        $nodeHelper = new EduSharingNodeHelper($this->helperBase);
        try {
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

        } catch ( Exception $e ) {
            if ( $e instanceof UsageDeletedException || $e instanceof NodeDeletedException ) {
                error_log( $e->getMessage() ); 
                return $this->getFakeNodeWithPreview( $postData->nodeId );
            } else {
                throw $e;
            }
        }
        
    }

    public function getTicket() {
        $ticket = '';

        // try and get ticket from cache
        $ticket = RequestContext::getMain()->getRequest()->getSession()->get('EduSharingRepoTicket_' . $this->config->username);
        if ( !is_null( $ticket ) ) {
            // check if ticket is still valid
            try {
                $ticketInfo = $this->authHelper->getTicketAuthenticationInfo( $ticket );
            } catch ( Exception $e ) {
                // something went wrong, e.g. cached ticket is not valid, so get a new one
            }

            if ( isset( $ticketInfo ) && $ticketInfo['statusCode'] == 'OK' ) {
                return $ticket;
            }
        } 

        // if we don't have a valid ticket or no ticket at all, we should get a new one
        $ticket = $this->doGetTicketAndCacheIt();
        return $ticket;
    }

    private function doGetTicketAndCacheIt() {

        $ticket = null;

        try {
            $ticket = $this->authHelper->getTicketForUser($this->config->username);
        } catch (Exception $e) {
            error_log( "Couldn't get ticket from Edusharing repository ($e)" );
        }

        // cache ticket if ok and return
        if ( ! is_null ( $ticket ) ) {
            RequestContext::getMain()->getRequest()->getSession()->set('EduSharingRepoTicket_' . $this->config->username, $ticket);
        }

        return $ticket;
    }


    public function encryptWithRepoKey( $data ) {
        
        $dataEncrypted = '';
        $key = $this->config->getRepoPublicKey();

        $repoPublicKey      = openssl_get_publickey( $key );
        $encryption_status  = openssl_public_encrypt( $data ,$dataEncrypted, $repoPublicKey );
        
        if( $encryption_status === false || $dataEncrypted === false ) {
            error_log('Encryption error');
            exit();
        }
        return $dataEncrypted;
    }


    private function getFakeNodeWithPreview( $nodeId ) {
        $node = [ 
            "node" => [ "mediatype" => "image" ],
            "detailsSnippet" => "<img src='{$this->config->baseUrl}/preview?nodeId={$nodeId}' />"
         ];

         return $node;
    }

}
?>
