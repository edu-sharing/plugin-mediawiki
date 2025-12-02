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
        if ( !$isAsset && $ticket ) {
            $forwardHeaders = array_filter(
                $forwardHeaders,
                static fn ( $h ) => stripos( $h, 'Authorization:' ) !== 0
            );
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

        try {
            $result = $eduService->helperBase->handleCurlRequest( $targetUrl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FAILONERROR => false,
                // avoid downstream compression issues; upstream can still compress
                CURLOPT_ENCODING => 'identity',
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $forwardHeaders,
                CURLOPT_POSTFIELDS => $body,
            ] );
        } catch ( \Throwable $e ) {
            $this->outputError( 500, 'Proxy failed: ' . $e->getMessage() );
            return;
        }

        $status = (int)( $result->info['http_code'] ?? 500 );
        http_response_code( $status ?: 500 );

        $contentType = $result->info['content_type'] ?? 'application/octet-stream';
        header( 'Content-Type: ' . $contentType );
        header( 'Access-Control-Allow-Origin: *' );
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
