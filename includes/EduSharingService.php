<?php
namespace MediaWiki\Extension\EduSharing;

use EduSharingApiClient\CurlResult;
use EduSharingApiClient\EduSharingAuthHelper;
use EduSharingApiClient\EduSharingHelperBase;
use EduSharingApiClient\EduSharingNodeHelper;
use EduSharingApiClient\EduSharingNodeHelperConfig;
use EduSharingApiClient\NodeDeletedException;
use EduSharingApiClient\PreviewSize;
use EduSharingApiClient\SecuredNode;
use EduSharingApiClient\UrlHandling;
use EduSharingApiClient\Usage;
use EduSharingApiClient\UsageDeletedException;
use MediaWiki\Config\Config;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\User;

require_once __DIR__ . '/../vendor/autoload.php';

class EduSharingService {
	/** @var array<string,array{ok:bool,msg:?string}> In-request compatibility cache */
	private static array $compatibilityCache = [];

	/** @var EduSharingConfig Extension configuration */
	public EduSharingConfig $config;
	/** @var EduSharingTicketManager Ticket manager */
	private EduSharingTicketManager $ticketManager;
	/** @var EduSharingHelperBase Low-level helper */
	public EduSharingHelperBase $helperBase;
	/** @var EduSharingNodeHelper|null Node helper */
	private $nodeHelper;
	/** @var bool Repository availability flag */
	public bool $isAvailable = true;
	/** @var string|null Last availability error message */
	public ?string $availabilityError = null;

	/**
	 * @param User $user Current user
	 * @param Config $mwConfig MediaWiki configuration
	 */
	public function __construct( User $user, Config $mwConfig ) {
		$this->config = new EduSharingConfig( $user, $mwConfig );
		if ( !$this->config->privateKey ) {
			$this->isAvailable = false;
			$this->availabilityError = wfMessage( 'edusharing-missing-keys-hint' )->inContentLanguage()->text();
			return;
		}

		$this->helperBase = new EduSharingHelperBase(
			$this->config->baseUrl,
			$this->config->privateKey,
			$this->config->appId
		);

		$compat = $this->verifyCompatibilityCached();
		if ( !$compat['ok'] ) {
			$this->isAvailable = false;
			$this->availabilityError = $compat['msg'] ?: 'edu-sharing repository unavailable';
			return;
		}

		$authHelper   = new EduSharingAuthHelper( $this->helperBase );
		$this->nodeHelper   = new EduSharingNodeHelper(
			$this->helperBase,
			new EduSharingNodeHelperConfig(
				new UrlHandling(
					true,
					SpecialPage::getTitleFor( 'EduProxy' )->getLocalURL()
				)
			)
		);
		$this->ticketManager = new EduSharingTicketManager(
			$authHelper,
			$this->config
		);
	}

	/**
	 * Verify repository compatibility with short-lived cache to avoid repeated backend calls.
	 *
	 * @return array{ok:bool,msg:?string}
	 */
	private function verifyCompatibilityCached(): array {
		$keySeed = $this->config->baseUrl . '|' . $this->config->appId;
		$cacheKey = md5( $keySeed );

		if ( isset( self::$compatibilityCache[$cacheKey] ) ) {
			return self::$compatibilityCache[$cacheKey];
		}

		$cache = \MediaWiki\MediaWikiServices::getInstance()->getMainWANObjectCache();
		$wanKey = $cache->makeKey( 'edusharing', 'compatibility', $cacheKey );
		$cached = $cache->get( $wanKey );
		if ( is_array( $cached ) && array_key_exists( 'ok', $cached ) ) {
			self::$compatibilityCache[$cacheKey] = [
				'ok' => (bool)$cached['ok'],
				'msg' => $cached['msg'] ?? null
			];
			return self::$compatibilityCache[$cacheKey];
		}

		$result = [ 'ok' => true, 'msg' => null ];
		try {
			$this->helperBase->verifyCompatibility();
		} catch ( \Throwable $e ) {
			$msg = trim( strtok( $e->getMessage(), "\n" ) ) ?: 'edu-sharing repository unavailable';
			$hasLocalKeys = (bool)$this->config->getPublicKey() && (bool)$this->config->getRepoPublicKey();
			if ( $hasLocalKeys && $msg && stripos( $msg, 'signature' ) !== false ) {
				$hint = wfMessage( 'edusharing-signature-invalid-hint' )->inContentLanguage()->text();
				if ( $hint ) {
					$msg .= ' - ' . $hint;
				}
			}
			$result = [ 'ok' => false, 'msg' => $msg ];
		}

		self::$compatibilityCache[$cacheKey] = $result;
		$cache->set( $wanKey, $result, 300 );

		return $result;
	}

	/**
	 * Get or create a ticket for the current user.
	 *
	 * @return string|null
	 */
	public function getTicket(): ?string {
		if ( !$this->isAvailable || !isset( $this->ticketManager ) ) {
			return null;
		}
		return $this->ticketManager->getTicket();
	}

	/**
	 * Create usage in the repository.
	 *
	 * @param \stdClass $postData Payload containing ticket/container/resource/nodeId
	 * @return mixed
	 */
	public function createUsage( $postData ) {
		if ( !$this->isAvailable ) {
			throw new \RuntimeException( 'edu-sharing backend unavailable' );
		}

		$result = $this->nodeHelper->createUsage(
			$postData->ticket,
			$postData->containerId,
			$postData->resourceId,
			$postData->nodeId
		);
		return $result;
	}

	/**
	 * Delete usage in the repository.
	 *
	 * @param \stdClass $postData Payload containing nodeId/usageId
	 * @return mixed
	 */
	public function deleteUsage( $postData ) {
		if ( !$this->isAvailable ) {
			throw new \RuntimeException( 'edu-sharing backend unavailable' );
		}

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

	/**
	 * Get a node by usage.
	 *
	 * @param \stdClass $postData Payload containing nodeId/nodeVersion/containerId/resourceId/usageId
	 * @return mixed
	 */
	public function getNode( $postData ) {
		if ( !$this->isAvailable ) {
			throw new \RuntimeException( 'edu-sharing backend unavailable' );
		}

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

	/**
	 * Get secured node information for a usage.
	 *
	 * @param Usage $usage
	 * @return SecuredNode
	 */
	public function getSecuredNodeByUsage( Usage $usage ): SecuredNode {
		if ( !$this->isAvailable ) {
			throw new \RuntimeException( 'edu-sharing backend unavailable' );
		}
		return $this->nodeHelper->getSecuredNodeByUsage( $usage );
	}

	/**
	 * Get the rendering service URL for direct calls.
	 *
	 * @return string
	 */
	public function getRenderingServiceUrl(): string {
		if ( !$this->isAvailable ) {
			throw new \RuntimeException( 'edu-sharing backend unavailable' );
		}
		$about = $this->helperBase->getAbout();
		if ( isset( $about['renderingService2']['url'] ) ) {
			return $about['renderingService2']['url'];
		}

		throw new \RuntimeException( 'Rendering Service 2 is not configured in edu-sharing.' );
	}

	/**
	 * Whether rendering service v2 is available.
	 *
	 * @return bool
	 */
	public function usesRenderingService2(): bool {
		try {
			$this->getRenderingServiceUrl();
			return true;
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	public function getRedirectUrl(
		string $mode,
		Usage $usage,
		array $additionalParams = [],
		?string $userId = null,
		bool $rendering2 = true
	): string {
		if ( !$this->isAvailable ) {
			throw new \RuntimeException( 'edu-sharing backend unavailable' );
		}
		return $this->nodeHelper->getRedirectUrl( $mode, $usage, $additionalParams, $userId, $rendering2 );
	}

	/**
	 * Get a preview for the given usage.
	 *
	 * @param Usage $usage
	 * @param PreviewSize $size
	 * @return CurlResult
	 */
	public function getPreview( Usage $usage, PreviewSize $size = PreviewSize::SIZE_400_PX ): CurlResult {
		if ( !$this->isAvailable ) {
			throw new \RuntimeException( 'edu-sharing backend unavailable' );
		}
		return $this->nodeHelper->getPreview( $usage, $size );
	}

	/**
	 * Encrypt data with the repository public key.
	 *
	 * @param string $data
	 * @return string
	 */
	public function encryptWithRepoKey( $data ) {
		$dataEncrypted = '';
		$key = $this->config->getRepoPublicKey();

		$repoPublicKey      = openssl_get_publickey( $key );
		$encryption_status  = openssl_public_encrypt( $data, $dataEncrypted, $repoPublicKey );

		if ( $encryption_status === false || $dataEncrypted === false ) {
			error_log( 'Encryption error' );
			exit();
		}
		return $dataEncrypted;
	}

		/**
		 * Build a fallback node with a preview snippet.
		 *
		 * @param string $nodeId
		 * @return array
		 */
	private function getFakeNodeWithPreview( $nodeId ) {
		$node = [
			"node" => [ "mediatype" => "image" ],
			"detailsSnippet" => "<img src='{$this->config->baseUrl}/preview?nodeId={$nodeId}' />"
		 ];

		return $node;
	}

}
