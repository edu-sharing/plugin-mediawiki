<?php
use MediaWiki\MediaWikiServices;

class EduSharingConfig {

    public $appId;
    public $baseUrl;
    public $username;
    public $privateKey;
    public $contentUrl;
    public $eduUrl;
    public $user;
    public $appType = 'LMS';
    public $appDomain;
    public $appHost;

    private $publicKey;
    private $repoPublicKey;
    private $privateKeyFile;
    private $publicKeyFile;
    private $repoPublicKeyFile;
    private $repoGuestUserName;
    private $repoForceGuestUser;

    public function __construct( $user ) {

        $config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'edusharing' );
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