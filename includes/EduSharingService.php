<?php
namespace MediaWiki\Extension\EduSharing;

use MediaWiki\Config\Config;
use MediaWiki\User\User;
use EduSharingApiClient\CurlResult;
use EduSharingApiClient\EduSharingAuthHelper;
use EduSharingApiClient\EduSharingHelperBase;
use EduSharingApiClient\EduSharingNodeHelper;
use EduSharingApiClient\EduSharingNodeHelperConfig; 
use EduSharingApiClient\PreviewSize;
use EduSharingApiClient\SecuredNode;
use EduSharingApiClient\UrlHandling;
use EduSharingApiClient\Usage;
use EduSharingApiClient\UsageDeletedException;
use EduSharingApiClient\NodeDeletedException;
use MediaWiki\SpecialPage\SpecialPage;


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
                                                            new UrlHandling(
                                                                true,
                                                                SpecialPage::getTitleFor( 'EduRenderProxy' )->getLocalURL()
                                                            )
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

    public function getSecuredNodeByUsage( Usage $usage ): SecuredNode {
        return $this->nodeHelper->getSecuredNodeByUsage( $usage );
    }

    public function getRenderingServiceUrl(): string {
        $about = $this->helperBase->getAbout();
        if ( isset( $about['renderingService2']['url'] ) ) {
            return $about['renderingService2']['url'];
        }

        throw new \RuntimeException( 'Rendering Service 2 is not configured in edu-sharing.' );
    }

    public function usesRenderingService2(): bool {
        try {
            $this->getRenderingServiceUrl();
            return true;
        } catch ( \Throwable $e ) {
            return false;
        }
    }

    public function getRedirectUrl( string $mode, Usage $usage, array $additionalParams = [], ?string $userId = null, bool $rendering2 = true ): string {
        return $this->nodeHelper->getRedirectUrl( $mode, $usage, $additionalParams, $userId, $rendering2 );
    }

    public function getPreview( Usage $usage, PreviewSize $size = PreviewSize::SIZE_400_PX ): CurlResult {
        return $this->nodeHelper->getPreview( $usage, $size );
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
