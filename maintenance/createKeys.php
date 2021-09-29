<?php
/**
 * Create a pair of private / public keys for edusharing extension 
 * and retrieve the public key from the configured edu-sharing - repository.
 * 
 * Usage: php createKeys.php [--regenerate-key-pair] [--get-repo-key-only]
 * where
 *   [--regenerate-key-pair] regenerates the keys even if there are exisitng ones
 *   [--get-repo-key-only] skips the key generation and only retrieves the repository's public key
 *
 * File location is configurable via LocalSettings.php, default is extensions/EduSharing/conf/.
 *
 * @file
 * @ingroup Maintenance
 */

if ( getenv( 'MW_INSTALL_PATH' ) ) {
    $IP = getenv( 'MW_INSTALL_PATH' );
} else {
    $IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";
require_once __DIR__ . "/../includes/edu-sharing-api/edu-sharing-helper.php";


class createEduSharingKeys extends Maintenance {

    private $privateKeyFile;
    private $publicKeyFile;
    private $repoPublicKeyFile;
    private $repoBaseUrl;

    public function __construct() {
        parent::__construct();
        $this->requireExtension( 'EduSharing' );
        $this->addDescription( "Create a new privat/public key pair anf retreive the edu-sharing repository's public key." );

        $this->addOption( "regenerate-key-pair", "generate a new pair of public / private keys, even if there are exisitng key files present" );
        $this->addOption( "get-repo-key-only", "Only retrieve public key from edu-sharing repository, don't generate key pair" );
    }

    public function execute() {
        global $IP;

        $privateKey                 = false;
        $publicKey                  = false;
        $repoPublicKey              = false;
        $this->privateKeyFile       = $IP . DIRECTORY_SEPARATOR . $GLOBALS[ 'wgEduSharingPrivateKeyFile' ];
        $this->publicKeyFile        = $IP . DIRECTORY_SEPARATOR . $GLOBALS[ 'wgEduSharingPublicKeyFile' ];
        $this->repoPublicKeyFile    = $IP . DIRECTORY_SEPARATOR . $GLOBALS[ 'wgEduSharingRepoPublicKeyFile' ];
        $this->repoBaseUrl          = $GLOBALS[ 'wgEduSharingBaseUrl' ];

        $privateKey = @file_get_contents( $this->privateKeyFile );
        $publicKey = @file_get_contents( $this->publicKeyFile );

        // generate key pair if indicated
        if( ( !$privateKey && !$publicKey ) || $this->getOption( 'regenerate-key-pair' ) !== null ) {
            
            if ( $this->getOption( 'get-repo-key-only' ) === null ) {
                $key = EduSharingHelper::generateKeyPair();

                file_put_contents( $this->publicKeyFile, $key[ 'publickey' ] );
                file_put_contents( $this->privateKeyFile, $key[ 'privatekey' ] );
                
                die( "Key files generated.\n" );
            } else {
                echo "Skipping key pair generation...\n";
            }

        } elseif ( $this->getOption( 'get-repo-key-only' ) === null ) {
            die( "Key files already exist. \nUse --regenerate-key-pair to generate a new pair. You will also have to update your repository registration in that case. \n" );
        }

        // retrieve public key from edu-sharing repository and save it to file
        $url = $this->repoBaseUrl . "/metadata?format=lms";
        $xml = @simpleXML_load_file( $url, "SimpleXMLElement" );

        if ( $xml === false )
            die( "Couldn't retrieve configuration data from repository\n" );

        $result = $xml->xpath('/properties/entry[@key="public_key"]');

        if ( $result ) {
            $repoPublicKey = $result[0];
            file_put_contents( $this->repoPublicKeyFile, $repoPublicKey );
            echo "Public key retrieved from edu-sharing - repository and written to file\n";
        } else {
            die( "Couldn't load repository public key from repository metadata url\n" );
        }
    }

}

$maintClass = createEduSharingKeys::class;
require_once RUN_MAINTENANCE_IF_MAIN;
?>