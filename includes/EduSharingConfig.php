<?php
namespace MediaWiki\Extension\EduSharing;

use MediaWiki\Config\Config;
use MediaWiki\User\User; 

class EduSharingConfig {

    public $appId;
    public $appDomain;
    public $appHost;    
    public $baseUrl;
    public $contentUrl;
    public $user;
    public $iconMimeAudio;
    public $iconMimeVideo;
    
    public $username;
    public $eduUrl;
    public $appType = 'LMS';

    public $privateKey;
    private $publicKey;
    private $repoPublicKey;
    private $privateKeyFile;
    private $publicKeyFile;
    private $repoPublicKeyFile;
    private $repoGuestUserName;
    private $repoForceGuestUser;
    public bool $enableServiceWorker;
    public string $repoId;
    public string $redirectEndpoint;
    public string $previewEndpoint;
    public bool $openResourceInNewTab;

    public function __construct( User $user, Config $config ) {

        $this->appId                = $config->get( 'EduSharingAppId' );
        $this->appDomain            = $config->get( 'EduSharingAppDomain' );
        $this->appHost              = $config->get( 'EduSharingAppHost' );
        $this->baseUrl              = $config->get( 'EduSharingBaseUrl' );
        $this->contentUrl           = $this->baseUrl . '/renderingproxy';
        $this->user                 = $user;
        $this->iconMimeAudio        = $config->get( 'EduSharingIconMimeAudio' );
        $this->iconMimeVideo        = $config->get( 'EduSharingIconMimeVideo' );
        $this->privateKeyFile       = $config->get( 'EduSharingPrivateKeyFile' );
        $this->publicKeyFile        = $config->get( 'EduSharingPublicKeyFile' );
        $this->repoPublicKeyFile    = $config->get( 'EduSharingRepoPublicKeyFile' );
        $this->repoGuestUserName    = $config->get( 'EduSharingGuestUserName' );
        $this->repoForceGuestUser   = $config->get( 'EduSharingForceGuestUser' );
        $this->enableServiceWorker  = (bool)$config->get( 'EduSharingEnableServiceWorker' );
        $this->repoId               = (string)$config->get( 'EduSharingRepoId' );
        $this->redirectEndpoint     = (string)$config->get( 'EduSharingRedirectEndpoint' );
        $this->previewEndpoint      = (string)$config->get( 'EduSharingPreviewEndpoint' );
        $this->openResourceInNewTab = (bool)$config->get( 'EduSharingOpenResourceInNewTab' );

        if ( empty( $user ) || filter_var( $user->getName(), FILTER_VALIDATE_IP ) !== false || $this->repoForceGuestUser === true )
            $this->username = $this->repoGuestUserName;
        else
            $this->username = trim( strtolower( $user->getName() ) );
        $this->loadPrivateKeyFromFile();
    }
    
    public function getPublicKey() {
        if ( !$this->publicKey ) 
            $this->loadPublicKeyFromFile();
        
        return $this->publicKey;
    }

    public function getRepoPublicKey() {
        if ( !$this->repoPublicKey ) 
            $this->loadRepoPublicKeyFromFile();
        
        return $this->repoPublicKey;
    }

    private function loadPrivateKeyFromFile() {

        $this->privateKey = @file_get_contents( $this->privateKeyFile );
        if ( !$this->privateKey )
            error_log( "no private key" );    
    }

    private function loadRepoPublicKeyFromFile() {

        $this->repoPublicKey = @file_get_contents( $this->repoPublicKeyFile );
        if ( !$this->repoPublicKey )
            error_log( "no repository public key" );
    }

    private function loadPublicKeyFromFile() {

        $this->publicKey = @file_get_contents( $this->publicKeyFile );
        if ( !$this->publicKey )
        error_log( "no public key" );
    }

}
