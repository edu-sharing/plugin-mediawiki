<?php
namespace MediaWiki\Extension\EduSharing;

use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use EduSharingApiClient\Usage;

class SpecialEduProxy extends SpecialPage {

    public function __construct() {
        parent::__construct( 'EduProxy', '', false );
    }

    public function execute( $par ) {
        $this->getOutput()->disable();

        $services = MediaWikiServices::getInstance();
        $config = $services->getConfigFactory()->makeConfig( 'edusharing' );
        $eduService = new EduSharingService( $this->getUser(), $config );

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
        if ( str_contains( $path, 'public/tracking' ) ) {
            http_response_code( 204 );
            header( 'Access-Control-Allow-Origin: *' );
            return;
        }

        $params = $this->getRequest()->getValues();
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

        $method = $this->getRequest()->getMethod();
        $body = file_get_contents( 'php://input' );

        $skipHeaders = [
            'host',
            'content-length',
            'origin',
            'referer',
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
        $session = $this->getRequest()->getSession();
        $sessionJwt = $session->get( 'edusharingJwt' );
        foreach ( getallheaders() as $name => $value ) {
            $nameLower = strtolower( $name );
            if ( in_array( $nameLower, $skipHeaders, true ) ) {
                continue;
            }
            if ( $nameLower === 'authorization' ) {
                $hasAuthHeader = true;
            }
            $forwardHeaders[] = $name . ': ' . $value;
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
        // Public job polling may also require auth; attach ticket if none is present
        // If we have a JWT stored from rendering, prefer forwarding it for job polling
        if ( $isPublic && str_contains( $path, 'public/job' ) ) {
            if ( !$hasAuthHeader && $sessionJwt ) {
                // Remove any previous Authorization we might have added above
                $forwardHeaders = array_filter(
                    $forwardHeaders,
                    static fn ( $h ) => stripos( $h, 'Authorization:' ) !== 0
                );
                $forwardHeaders[] = 'Authorization: Bearer ' . $sessionJwt;
            } elseif ( !$hasAuthHeader && $ticket ) {
                $forwardHeaders[] = 'Authorization: EDU-TICKET ' . $ticket;
                $forwardHeaders[] = 'X-Edu-App-Id: ' . $eduService->config->appId;
            }
        }

        // Add signing headers for REST endpoints
        if ( $isRest ) {
            $ts = (int)( microtime( true ) * 1000 );
            $toSign = $eduService->config->appId . $targetUrl . $ts;
            $signature = $eduService->helperBase->sign( $toSign );
            $forwardHeaders[] = 'X-Edu-App-Signed: ' . $toSign;
            $forwardHeaders[] = 'X-Edu-App-Sig: ' . $signature;
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
                $forwardHeaders[] = 'X-Edu-App-Ts: ' . $ts;
                $forwardHeaders[] = 'X-Edu-Usage-Node-Id: ' . $usage->nodeId;
                $forwardHeaders[] = 'X-Edu-Usage-Course-Id: ' . $usage->containerId;
                $forwardHeaders[] = 'X-Edu-Usage-Resource-Id: ' . $usage->resourceId;
                if ( $usage->nodeVersion !== null && $usage->nodeVersion !== '' ) {
                    $forwardHeaders[] = 'X-Edu-Usage-Node-Version: ' . $usage->nodeVersion;
                }
            }
        }

        if ( $isPublic && str_contains( $path, 'public/job' ) ) {
            $logHeaders = array_map(
                static fn ( $h ) => stripos( $h, 'Authorization:' ) === 0 ? 'Authorization: [redacted]' : $h,
                $forwardHeaders
            );
            wfDebugLog( 'edusharing', 'Public job proxy ' . json_encode( [
                'method' => $method,
                'path' => $path,
                'targetUrl' => $targetUrl,
                'headers' => $logHeaders,
            ], JSON_UNESCAPED_SLASHES ) );
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

        if ( $isPublic && str_contains( $path, 'public/job' ) ) {
            $bodyPreview = $result->content;
            if ( strlen( $bodyPreview ) > 300 ) {
                $bodyPreview = substr( $bodyPreview, 0, 300 ) . '...';
            }
            wfDebugLog( 'edusharing', 'Public job proxy result ' . json_encode( [
                'status' => (int)( $result->info['http_code'] ?? 0 ),
                'contentType' => $result->info['content_type'] ?? null,
                'body' => $bodyPreview,
            ], JSON_UNESCAPED_SLASHES ) );
        }

        $status = (int)( $result->info['http_code'] ?? 500 );
        http_response_code( $status ?: 500 );

        $contentType = $result->info['content_type'] ?? 'application/octet-stream';
        header( 'Content-Type: ' . $contentType );
        header( 'Access-Control-Allow-Origin: *' );
        if ( !empty( $responseHeaders ) ) {
            $host = $this->getRequest()->getHeader( 'Host' ) ?: parse_url( $this->getRequest()->getFullRequestURL(), PHP_URL_HOST );
            foreach ( $responseHeaders as $hdr ) {
                if ( stripos( $hdr, 'Set-Cookie:' ) === 0 ) {
                    $cookie = $hdr;
                    if ( $host && stripos( $cookie, 'Domain=' ) !== false ) {
                        // Rewrite upstream cookie domain to current host so the browser accepts it
                        $cookie = preg_replace( '/Domain=[^;]+/i', 'Domain=' . $host, $cookie );
                    }
                    header( $cookie, false );
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
