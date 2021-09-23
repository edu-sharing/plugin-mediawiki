<?php
use MediaWiki\MediaWikiServices;

class EduSharingConfig {

    public $appId;
    public $baseUrl;
    public $username;
    public $privateKey;
    public $contentUrl;
    public $eduUrl;
    public $repoPublicKey;
    public $user;
    
    private $privateKeyFile = __DIR__ . '/../conf/private.key';
    private $repoPublicKeyFile = __DIR__ . '/../conf/repopublic.key';

    public function __construct( $user ) {

        $config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'edusharing' );
        $this->appId            = $config->get( 'EduSharingAppId' );
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
        $this->loadRepoPublicKeyFromFile();
    }
    

    private function loadPrivateKeyFromFile() {

        $this->privateKey = @file_get_contents( $this->privateKeyFile );
        if ( !$this->privateKey )
            die('no private key');   
    }

    private function loadRepoPublicKeyFromFile() {

        $this->repoPublicKey = @file_get_contents( $this->repoPublicKeyFile );
        if ( !$this->repoPublicKey )
            die('no public repo key');   
    }

}