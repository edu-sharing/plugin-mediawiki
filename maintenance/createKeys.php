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

require_once __DIR__ . '/../vendor/edu-sharing/auth-plugin/src/EduSharing/EduSharingHelper.php';

use EduSharingApiClient\EduSharingHelper;
use MediaWiki\MediaWikiServices;

class createEduSharingKeys extends Maintenance {

	private $privateKeyFile;
	private $publicKeyFile;
	private $repoPublicKeyFile;
	private $repoBaseUrl;

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'EduSharing' );
		$this->addDescription( "Create a new private/public key pair and retrieve the edu-sharing repository's public key." );

		$this->addOption( "regenerate-key-pair", "Generate a new pair of public/private keys even if key files already exist" );
		$this->addOption( "get-repo-key-only", "Only retrieve the repository public key; do not generate a key pair" );
	}

	public function execute() {
		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'edusharing' );

		$this->privateKeyFile = MW_INSTALL_PATH . DIRECTORY_SEPARATOR . $config->get( 'EduSharingPrivateKeyFile' );
		$this->publicKeyFile = MW_INSTALL_PATH . DIRECTORY_SEPARATOR . $config->get( 'EduSharingPublicKeyFile' );
		$this->repoPublicKeyFile = MW_INSTALL_PATH . DIRECTORY_SEPARATOR . $config->get( 'EduSharingRepoPublicKeyFile' );
		$this->repoBaseUrl = rtrim( $config->get( 'EduSharingBaseUrl' ), '/' );

		$existingPrivate = @file_get_contents( $this->privateKeyFile );
		$existingPublic = @file_get_contents( $this->publicKeyFile );

		$regenerate = $this->getOption( 'regenerate-key-pair' ) !== null;
		$repoOnly = $this->getOption( 'get-repo-key-only' ) !== null;

		// Generate key pair if requested or missing
		if ( ( !$existingPrivate && !$existingPublic ) || $regenerate ) {
			if ( !$repoOnly ) {
				$keyPair = EduSharingHelper::generateKeyPair();
				// New helper returns camelCase keys
				file_put_contents( $this->publicKeyFile, $keyPair['publicKey'], LOCK_EX );
				file_put_contents( $this->privateKeyFile, $keyPair['privateKey'], LOCK_EX );
				$this->output( "Key files generated.\n" );
			} else {
				$this->output( "Skipping key pair generation (--get-repo-key-only).\n" );
			}
		} elseif ( !$repoOnly ) {
			$this->fatalError( "Key files already exist. Use --regenerate-key-pair to generate a new pair (requires re-registering in the repository)." );
		}

		// Retrieve public key from edu-sharing repository and save it to file
		$url = $this->repoBaseUrl . "/metadata?format=lms";
		$httpReqFactory = $services->getHttpRequestFactory();
		$request = $httpReqFactory->create( $url, [ 'method' => 'GET', 'timeout' => 15 ] );
		$status = $request->execute();
		if ( !$status->isOK() ) {
			$this->fatalError( "Couldn't retrieve configuration data from repository: " . $status->getWikiText() );
		}
		$content = $request->getContent();

		$xml = @simplexml_load_string( $content, "SimpleXMLElement", LIBXML_NOCDATA );
		if ( $xml === false ) {
			$this->fatalError( "Couldn't parse repository metadata." );
		}

		$result = $xml->xpath( '/properties/entry[@key="public_key"]' );

		if ( $result && isset( $result[0] ) ) {
			$repoPublicKey = (string)$result[0];
			file_put_contents( $this->repoPublicKeyFile, $repoPublicKey, LOCK_EX );
			$this->output( "Public key retrieved from edu-sharing repository and written to file\n" );
		} else {
			$this->fatalError( "Couldn't find repository public key in repository metadata." );
		}
	}

}

$maintClass = createEduSharingKeys::class;
require_once RUN_MAINTENANCE_IF_MAIN;
