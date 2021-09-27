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

    private $privateKeyFile = __DIR__ . '/../conf/private.key';
    private $publicKeyFile = __DIR__ . '/../conf/public.key';
    private $repoPublicKeyFile = __DIR__ . '/../conf/repopublic.key';

    public function __construct( $user ) {

        $config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'edusharing' );
        $this->appId            = $config->get( 'EduSharingAppId' );
        $this->appDomain        = $config->get( 'EduSharingAppDomain' );
        $this->appHost          = $config->get( 'EduSharingAppHost' );
        $this->baseUrl          = $config->get( 'EduSharingBaseUrl' );
        $this->contentUrl       = $this->baseUrl . '/renderingproxy';
        $this->user             = $user;
        $this->iconMimeAudio    = $config->get( 'EduSharingIconMimeAudio' );
        $this->iconMimeVideo    = $config->get( 'EduSharingIconMimeVideo' );

        if ( empty( $user ) || filter_var( $user->getName(), FILTER_VALIDATE_IP ) !== false )
            $this->username = 'mw_guest';
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
            $this->loadRepoPublicKey();
        
        return $this->repoPublicKey;
    }

    private function loadPrivateKeyFromFile() {

        $this->privateKey = @file_get_contents( $this->privateKeyFile );
        if ( !$this->privateKey )
            error_log( "no private key" );    
    }

    private function loadRepoPublicKey() {

        $this->repoPublicKey = @file_get_contents( $this->repoPublicKeyFile );
        $url = $this->baseUrl . "/metadata?format=lms";
        $xml = @simpleXML_load_file( $url,"SimpleXMLElement" );

        if ( $xml === false )
            error_log( "couldn't retrieve configuration data from repository" );

        $result = $xml->xpath('/properties/entry[@key="public_key"]');

        if ( $result ) {
            $this->repoPublicKey = $result[0];
        } else {
            error_log( "couldn't load repository public key from repository metadata url" );
        }

    }

    private function loadPublicKeyFromFile() {

        $this->publicKey = @file_get_contents( $this->publicKeyFile );
        if ( !$this->publicKey )
        error_log( "no public key" );
    }

}