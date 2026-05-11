<?php
namespace MediaWiki\Extension\EduSharing;

use EduSharingApiClient\Usage;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;

class SpecialEduProxy extends SpecialPage {

	/**
	 * Proxy requests to edu-sharing rendering/API endpoints, handling cookies and headers.
	 */
	public function __construct() {
		parent::__construct( 'EduProxy', '', false );
	}

	/**
	 * Proxy a request to the configured edu-sharing rendering/API endpoints.
	 *
	 * @param string|null $par Remaining path to proxy
	 */
	public function execute( $par ) {
		$this->getOutput()->disable();

		$services = MediaWikiServices::getInstance();
		$config = $services->getConfigFactory()->makeConfig( 'edusharing' );
		$eduService = new EduSharingService( $this->getUser(), $config );
		$request = $this->getRequest();

		if ( !$eduService->isAvailable ) {
			$this->outputError( 503, 'edu-sharing backend unavailable' );
			return;
		}

		$path = ltrim( (string)$par, '/' );
		$isRest = str_starts_with( $path, 'rest/' );
		$isAsset = str_starts_with( $path, 'web-components/rendering-service' );
		$isPreview = str_starts_with( $path, 'public/preview' );
		$isPublic = str_starts_with( $path, 'public/' );

		$targetUrl = $this->resolveTarget( $eduService, $path );
		if ( !$targetUrl ) {
			$this->outputError( 400, 'Invalid proxy target' );
			return;
		}

		$params = $request->getValues();
		unset( $params['title'] );
		if ( $params ) {
			// ensure repoId is forwarded for rendering service calls (except preview which uses baseUrl)
			if ( $isPublic && !$isPreview && !isset( $params['repoId'] ) && $eduService->config->repoId
			) {
				$params['repoId'] = $eduService->config->repoId;
			}
			$targetUrl .= ( str_contains( $targetUrl, '?' ) ? '&' : '?' ) . wfArrayToCgi( $params );
		}

		// Redirect handling: generate repo redirect and send it to client
		if ( !$isAsset && str_starts_with( $path, 'public/redirect' ) ) {
			$usage = $this->requestToUsage( $params );
			if ( $usage ) {
				try {
					$mode = $params['mode'] ?? 'content';
					$redirectUrl = $eduService->getRedirectUrl(
						mode: $mode,
						usage: $usage,
						additionalParams: [],
						userId: null,
						rendering2: true
					);
					header( 'Location: ' . $redirectUrl, true, 302 );
					return;
				} catch ( \Throwable $e ) {
					$this->outputError( 500, 'Redirect generation failed: ' . $e->getMessage() );
					return;
				}
			}
		}

		$method = $request->getMethod();
		$body = file_get_contents( 'php://input' );

		$skipHeaders = [
			'host',
			'content-length',
			'sec-fetch-mode',
			'sec-fetch-site',
			'sec-fetch-dest',
			'sec-fetch-user',
			'sec-ch-ua',
			'sec-ch-ua-mobile',
			'sec-ch-ua-platform',
			'access-control-request-method',
			'access-control-request-headers',
			'accept-encoding', // we force identity
		];

		$forwardHeaders = [];
		$hasAuthHeader = false;
		$hasOriginHeader = false;
		foreach ( getallheaders() as $name => $value ) {
			$nameLower = strtolower( $name );
			if ( in_array( $nameLower, $skipHeaders, true ) ) {
				continue;
			}
			if ( $nameLower === 'authorization' ) {
				$hasAuthHeader = true;
			}
			if ( $nameLower === 'origin' ) {
				$hasOriginHeader = true;
			}
			$forwardHeaders[] = $name . ': ' . $value;
		}
		if ( !$hasOriginHeader ) {
			$reqUrl = $request->getFullRequestURL();
			$parts = parse_url( $reqUrl );
			$hasSchemeAndHost = isset( $parts['scheme'] ) && isset( $parts['host'] );
			if ( $hasSchemeAndHost ) {
				$origin = $parts['scheme'] . '://' . $parts['host'];
				if ( isset( $parts['port'] ) ) {
					$origin .= ':' . $parts['port'];
				}
				$forwardHeaders[] = 'Origin: ' . $origin;
			}
		}
		// Add edu-sharing auth headers (not for static assets)
		$ticket = $eduService->getTicket();
		if ( $isRest && !$isAsset && $ticket && !$hasAuthHeader ) {
			$forwardHeaders = array_filter(
				$forwardHeaders,
				static fn ( $h ) => stripos( $h, 'Authorization:' ) !== 0
			);
			$forwardHeaders[] = 'Authorization: EDU-TICKET ' . $ticket;
			$forwardHeaders[] = 'X-Edu-App-Id: ' . $eduService->config->appId;
		}
		// Public endpoints: keep client auth; only fall back to ticket if none is present
		if ( $isPublic
				&& ( str_contains( $path, 'public/job' ) || str_contains( $path, 'public/renderdata' ) )
				&& !$hasAuthHeader && $ticket
		) {
			$forwardHeaders[] = 'Authorization: EDU-TICKET ' . $ticket;
			$forwardHeaders[] = 'X-Edu-App-Id: ' . $eduService->config->appId;
		}

		// Add signing headers for REST endpoints
		if ( $isRest ) {
			$ts = (int)( microtime( true ) * 1000 );
			$toSign = $eduService->config->appId . $targetUrl . $ts;
			$signature = $eduService->helperBase->sign( $toSign );
			$forwardHeaders[] = 'X-Edu-App-Signed: ' . $toSign;
			$forwardHeaders[] = 'X-Edu-App-Sig: ' . $signature;
            $forwardHeaders[] = 'X-Edu-App-SignedAlg: ' . $eduService->helperBase->signatureHandler->getAlgorithm();
            $forwardHeaders[] = 'X-Edu-App-Ts: ' . $ts;
		}

		// Usage signature headers for rendering public endpoints (preview/redirect)
		if ( !$isAsset && ( $isPreview || str_contains( $path, 'public/redirect' ) ) ) {
			$usage = $this->requestToUsage( $params );
			if ( $usage ) {
				$ts = (int)( microtime( true ) * 1000 );
				$toSign = $eduService->config->appId . $usage->usageId . $ts;
				$signature = $eduService->helperBase->sign( $toSign );
				$forwardHeaders[] = 'X-Edu-App-Signed: ' . $toSign;
				$forwardHeaders[] = 'X-Edu-App-Sig: ' . $signature;
                $forwardHeaders[] = 'X-Edu-App-SignedAlg: ' . $eduService->helperBase->signatureHandler->getAlgorithm();
				$forwardHeaders[] = 'X-Edu-App-Ts: ' . $ts;
				$forwardHeaders[] = 'X-Edu-Usage-Node-Id: ' . $usage->nodeId;
				$forwardHeaders[] = 'X-Edu-Usage-Course-Id: ' . $usage->containerId;
				$forwardHeaders[] = 'X-Edu-Usage-Resource-Id: ' . $usage->resourceId;
				if ( $usage->nodeVersion !== null && $usage->nodeVersion !== '' ) {
					$forwardHeaders[] = 'X-Edu-Usage-Node-Version: ' . $usage->nodeVersion;
				}
			}
		}

		if ( $isPublic && ( str_contains( $path, 'public/job' ) || str_contains( $path, 'public/renderdata' ) ) ) {
			$logHeaders = array_map(
				static fn ( $h ) => stripos( $h, 'Authorization:' ) === 0 ? 'Authorization: [redacted]' : $h,
				$forwardHeaders
			);
			$label = str_contains( $path, 'public/job' ) ? 'Public job proxy' : 'Public renderdata proxy';
		}

		try {
			$responseHeaders = [];
			$headerFn = static function ( $ch, $header ) use ( &$responseHeaders ) {
				$len = strlen( $header );
				// Reset on new response (handles redirects)
				if ( stripos( $header, 'HTTP/' ) === 0 ) {
					$responseHeaders = [];
				}
				$trimmed = trim( $header );
				if ( $trimmed !== '' ) {
					$responseHeaders[] = $trimmed;
				}
				return $len;
			};
			$result = $eduService->helperBase->handleCurlRequest( $targetUrl, [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_FAILONERROR => false,
				// avoid downstream compression issues; upstream can still compress
				CURLOPT_ENCODING => 'identity',
				CURLOPT_CUSTOMREQUEST => $method,
				CURLOPT_HTTPHEADER => $forwardHeaders,
				CURLOPT_POSTFIELDS => $body,
				CURLOPT_HEADERFUNCTION => $headerFn,
			] );
		} catch ( \Throwable $e ) {
			$this->outputError( 500, 'Proxy failed: ' . $e->getMessage() );
			return;
		}

		if ( $isPublic && ( str_contains( $path, 'public/job' ) || str_contains( $path, 'public/renderdata' ) ) ) {
			$bodyPreview = $result->content;
			if ( strlen( $bodyPreview ) > 300 ) {
				$bodyPreview = substr( $bodyPreview, 0, 300 ) . '...';
			}
			$label = str_contains( $path, 'public/job' ) ? 'Public job proxy result' : 'Public renderdata proxy result';
		}

		$status = (int)( $result->info['http_code'] ?? 500 );
		http_response_code( $status ?: 500 );

		$contentType = $result->info['content_type'] ?? 'application/octet-stream';
		header( 'Content-Type: ' . $contentType );
		header( 'Access-Control-Allow-Origin: *' );
		if ( isset( $responseHeaders ) && $responseHeaders !== [] ) {
			$host = $request->getHeader( 'Host' ) ?: parse_url( $request->getFullRequestURL(), PHP_URL_HOST );
			foreach ( $responseHeaders as $hdr ) {
				if ( stripos( $hdr, 'Set-Cookie:' ) === 0 ) {
					$cookie = $hdr;
					if ( $host && stripos( $cookie, 'Domain=' ) !== false ) {
						// Rewrite upstream cookie domain to current host so the browser accepts it
						$cookie = preg_replace( '/Domain=[^;]+/i', 'Domain=' . $host, $cookie );
					}
					if ( stripos( $cookie, 'Path=/rendering' ) !== false ) {
						// Broaden path so cookie is sent to /wiki/Spezial:EduProxy/*
						$cookie = preg_replace( '/Path=\\/rendering/i', 'Path=/', $cookie );
					}
					header( $cookie, false );
					continue;
				}
				if ( stripos( $hdr, 'Authentication-Info:' ) === 0 ) {
					header( $hdr, false );
				}
			}
		}
		if ( str_contains( $path, 'edu-service-worker.js' ) ) {
			header( 'Service-Worker-Allowed: /' );
		}

		echo $result->content;
	}

	private function resolveTarget( EduSharingService $eduService, string $path ): ?string {
		$base = rtrim( $eduService->config->baseUrl, '/' );
		if ( str_starts_with( $path, 'rest/' ) ) {
			return $base . '/' . $path;
		}
		if ( str_starts_with( $path, 'web-components/rendering-service' ) ) {
			// Assets are served from the repository host, not the rendering service
			return $base . '/' . $path;
		}
		if ( str_starts_with( $path, 'public/preview' ) ) {
			return $base . '/preview';
		}
		if ( str_starts_with( $path, 'public/' ) ) {
			$rendering = $eduService->getRenderingServiceUrl();
			if ( $rendering ) {
				return rtrim( $rendering, '/' ) . '/' . $path;
			}
		}
		return null;
	}

	private function requestToUsage( array $params ): ?Usage {
		$nodeId = $params['nodeId'] ?? null;
		$containerId = $params['containerId'] ?? null;
		$resourceId = $params['resourceId'] ?? null;
		$usageId = $params['usageId'] ?? null;
		if ( !$nodeId || !$containerId || !$resourceId || !$usageId ) {
			return null;
		}
		$nodeVersion = $params['nodeVersion'] ?? null;
		return new Usage( $nodeId, $nodeVersion, $containerId, $resourceId, $usageId );
	}

	private function outputError( int $code, string $message ): void {
		http_response_code( $code );
		header( 'Content-Type: text/plain' );
		echo $message;
	}
}
