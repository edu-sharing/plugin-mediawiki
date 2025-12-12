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

	public EduSharingConfig $config;
	private EduSharingTicketManager $ticketManager;
	public EduSharingHelperBase $helperBase;
	private $nodeHelper;
	public bool $isAvailable = true;
	public ?string $availabilityError = null;

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

		try {
			$this->helperBase->verifyCompatibility();
		} catch ( \Throwable $e ) {
			$this->isAvailable = false;
			$msg = trim( strtok( $e->getMessage(), "\n" ) ) ?: 'edu-sharing repository unavailable';
			// If keys exist but signature verification fails, offer guidance
			$hasLocalKeys = (bool)$this->config->getPublicKey() && (bool)$this->config->getRepoPublicKey();
			if ( $hasLocalKeys && $msg && stripos( $msg, 'signature' ) !== false ) {
				$hint = wfMessage( 'edusharing-signature-invalid-hint' )->inContentLanguage()->text();
				if ( $hint ) {
					$msg .= ' - ' . $hint;
				}
			}
			$this->availabilityError = $msg;
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

	public function getTicket(): ?string {
		if ( !$this->isAvailable || !isset( $this->ticketManager ) ) {
			return null;
		}
		return $this->ticketManager->getTicket();
	}

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

	public function getSecuredNodeByUsage( Usage $usage ): SecuredNode {
		if ( !$this->isAvailable ) {
			throw new \RuntimeException( 'edu-sharing backend unavailable' );
		}
		return $this->nodeHelper->getSecuredNodeByUsage( $usage );
	}

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

	public function getPreview( Usage $usage, PreviewSize $size = PreviewSize::SIZE_400_PX ): CurlResult {
		if ( !$this->isAvailable ) {
			throw new \RuntimeException( 'edu-sharing backend unavailable' );
		}
		return $this->nodeHelper->getPreview( $usage, $size );
	}

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

	private function getFakeNodeWithPreview( $nodeId ) {
		$node = [
			"node" => [ "mediatype" => "image" ],
			"detailsSnippet" => "<img src='{$this->config->baseUrl}/preview?nodeId={$nodeId}' />"
		 ];

		 return $node;
	}

}
