<?php
namespace MediaWiki\Extension\EduSharing;

use MediaWiki\Config\Config;
use MediaWiki\User\User;

class EduSharingConfig {

	/** @var string Application ID registered in the repository */
	public $appId;
	/** @var string Fully qualified domain of the wiki */
	public $appDomain;
	/** @var string Public IP/host of the wiki */
	public $appHost;
	/** @var string Base URL of the edu-sharing repository */
	public $baseUrl;
	/** @var string Content URL (legacy rendering proxy) */
	public $contentUrl;
	/** @var \MediaWiki\User\User Current user */
	public $user;
	/** @var string URL to audio icon */
	public $iconMimeAudio;
	/** @var string URL to video icon */
	public $iconMimeVideo;

	/** @var string Repository username (guest or real user) */
	public $username;
	/** @var string|null Legacy edu URL (unused) */
	public $eduUrl;
	/** @var string Application type */
	public $appType = 'LMS';

	/** @var string|null Private key content */
	public $privateKey;
	/** @var string|null Public key content */
	private $publicKey;
	/** @var string|null Repository public key content */
	private $repoPublicKey;
	/** @var string Path to private key file */
	private $privateKeyFile;
	/** @var string Path to public key file */
	private $publicKeyFile;
	/** @var string Path to repository public key file */
	private $repoPublicKeyFile;
	/** @var string Guest username configured in repo */
	private $repoGuestUserName;
	/** @var bool Force use of guest user */
	private $repoForceGuestUser;
	/** @var bool Enable registration of service worker */
	public bool $enableServiceWorker;
	/** @var string Repository ID for rendering service */
	public string $repoId;
	/** @var string Redirect endpoint override */
	public string $redirectEndpoint;
	/** @var string Preview endpoint override */
	public string $previewEndpoint;
	/** @var bool Open resource links in new tab */
	public bool $openResourceInNewTab;

	/**
	 * @param User $user Current user
	 * @param Config $config MediaWiki configuration
	 */
	public function __construct( User $user, Config $config ) {
		$this->appId                = $config->get( 'EduSharingAppId' );
		$this->appDomain            = $config->get( 'EduSharingAppDomain' );
		$this->appHost              = $config->get( 'EduSharingAppHost' );
		$this->baseUrl              = $config->get( 'EduSharingBaseUrl' );
		$this->contentUrl           = $this->baseUrl . '/renderingproxy';
		$this->user                 = $user;
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

		if (
				!isset( $user ) ||
				!$user ||
				filter_var( $user->getName(), FILTER_VALIDATE_IP ) !== false ||
				$this->repoForceGuestUser === true
			) {
			$this->username = $this->repoGuestUserName;
		} else {
			$this->username = trim( strtolower( $user->getName() ) );
		}
		$this->loadPrivateKeyFromFile();
	}

	/**
	 * Get the local public key content.
	 *
	 * @return string|null
	 */
	public function getPublicKey() {
		if ( !$this->publicKey ) {
			$this->loadPublicKeyFromFile();
		}

		return $this->publicKey;
	}

	/**
	 * Get the repository public key content.
	 *
	 * @return string|null
	 */
	public function getRepoPublicKey() {
		if ( !$this->repoPublicKey ) {
			$this->loadRepoPublicKeyFromFile();
		}

		return $this->repoPublicKey;
	}

	private function loadPrivateKeyFromFile() {
		$this->privateKey = file_get_contents( $this->privateKeyFile );
		if ( !$this->privateKey ) {
			error_log( "no private key" );
		}
	}

	private function loadRepoPublicKeyFromFile() {
		$this->repoPublicKey = file_get_contents( $this->repoPublicKeyFile );
		if ( !$this->repoPublicKey ) {
			error_log( "no repository public key" );
		}
	}

	private function loadPublicKeyFromFile() {
		$this->publicKey = file_get_contents( $this->publicKeyFile );
		if ( !$this->publicKey ) {
			error_log( "no public key" );
		}
	}

}
