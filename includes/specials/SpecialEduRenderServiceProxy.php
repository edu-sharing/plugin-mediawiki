<?php
namespace MediaWiki\Extension\EduSharing;

use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\MediaWikiServices;

class SpecialEduRenderServiceProxy extends SpecialPage {

    public function __construct() {
        parent::__construct( 'EduRenderServiceProxy', '', false );
    }

    public function execute( $par ) {
        $this->getOutput()->disable();

        $services = MediaWikiServices::getInstance();
        $config = $services->getConfigFactory()->makeConfig( 'edusharing' );
        $eduService = new EduSharingService( $this->getUser(), $config );

        $base = rtrim( $eduService->getRenderingServiceUrl(), '/' );
        $path = ltrim( (string)$par, '/' );
        $targetUrl = $base . ( $path !== '' ? '/' . $path : '' );

        $params = $this->getRequest()->getValues();
        unset( $params['title'] );
        if ( $params ) {
            $targetUrl .= '?' . wfArrayToCgi( $params );
        }

        $method = $this->getRequest()->getMethod();
        $body = file_get_contents( 'php://input' );
        $forwardHeaders = [];
        foreach ( getallheaders() as $name => $value ) {
            $nameLower = strtolower( $name );
            if ( in_array( $nameLower, [ 'host', 'content-length' ], true ) ) {
                continue;
            }
            $forwardHeaders[] = $name . ': ' . $value;
        }

        try {
            $result = $eduService->helperBase->handleCurlRequest( $targetUrl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_FAILONERROR => false,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $forwardHeaders,
                CURLOPT_POSTFIELDS => $body,
            ] );
        } catch ( \Throwable $e ) {
            $this->outputError( 500, 'Proxy failed: ' . $e->getMessage() );
            return;
        }

        $status = (int)( $result->info['http_code'] ?? 500 );
        if ( $status >= 400 || $result->error !== 0 ) {
            // Bubble up upstream status to help debugging rather than blocking with 401 locally.
            http_response_code( $status ?: 500 );
        }

        $contentType = $result->info['content_type'] ?? 'application/octet-stream';
        header( 'Content-Type: ' . $contentType );
        header( 'Access-Control-Allow-Origin: *' );
        echo $result->content;
    }

    private function outputError( int $code, string $message ): void {
        http_response_code( $code );
        header( 'Content-Type: text/plain' );
        echo $message;
    }
}
