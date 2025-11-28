<?php
namespace MediaWiki\Extension\EduSharing;

use MediaWiki\Config\Config;
use MediaWiki\User\User;
use EduSharingApiClient\EduSharingHelperBase;
use EduSharingApiClient\EduSharingAuthHelper;
use EduSharingApiClient\EduSharingNodeHelper;
use EduSharingApiClient\EduSharingNodeHelperConfig; 
use EduSharingApiClient\UrlHandling;
use EduSharingApiclient\UsageDeletedException;
use EduSharingApiclient\Usage;
use EduSharingApiclient\NodeDeletedException;


require_once __DIR__ . '/../vendor/autoload.php';

class EduSharingService {

    public EduSharingConfig $config;
    private EduSharingTicketManager $ticketManager;    
    public EduSharingHelperBase $helperBase;
    private $nodeHelper;

    public function __construct( User $user, Config $mwConfig ) {

        $this->config       = new EduSharingConfig( $user, $mwConfig );
        $this->helperBase   = new EduSharingHelperBase( $this->config->baseUrl, $this->config->privateKey, $this->config->appId );

        $this->helperBase->verifyCompatibility();

        $authHelper   = new EduSharingAuthHelper( $this->helperBase );
        $this->nodeHelper   = new EduSharingNodeHelper( $this->helperBase, 
                                                        new EduSharingNodeHelperConfig(
                                                            new UrlHandling(true, 'example-api.php?action=REDIRECT')
                                                        )
                                                    );
        $this->ticketManager = new EduSharingTicketManager(
            $authHelper,
            $this->config
        );
    }

    public function getTicket(): ?string {
        return $this->ticketManager->getTicket();
    }    
   
    public function createUsage( $postData)  {

        $result = $this->nodeHelper->createUsage(
            $postData->ticket,
            $postData->containerId,
            $postData->resourceId,
            $postData->nodeId
        );
        return $result;
    }


    public function deleteUsage( $postData ) {

        try {
            $result = $this->nodeHelper->deleteUsage(
                $postData->nodeId,
                $postData->usageId
            );    
            return $result;

        } catch ( \Exception $e ) {
            if ( $e instanceof UsageDeletedException ) {
                error_log( 'noted, deleting locally: ' . $e->getMessage() ); 
            } else {
                throw $e;
            }            
        }
    }


    public function getNode($postData) {

        try {
            $result = $this->nodeHelper->getNodeByUsage(
                new Usage(
                    $postData->nodeId,
                    $postData->nodeVersion,
                    $postData->containerId,
                    $postData->resourceId,
                    $postData->usageId
                )
            );
            return $result;

        } catch ( \Exception $e ) {
            if ( $e instanceof UsageDeletedException || $e instanceof NodeDeletedException ) {
                error_log( $e->getMessage() ); 
                return $this->getFakeNodeWithPreview( $postData->nodeId );
            } else {
                throw $e;
            }
        }
        
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