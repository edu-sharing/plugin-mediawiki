<?php
class SpecialEduInlineHelper extends SpecialPage {

    function __construct() {
		parent::__construct( 'EduInlineHelper', '', false );
	}

    public function execute( $par ) {

        $request = $this->getRequest();

        $redirect_url = $request->getVal( 'reUrl' );
        $parts = parse_url( $redirect_url );
        parse_str( $parts[ 'query' ], $query );

        $eduService = new EduSharingService();

        $paramString = '';
        $ts = round ( microtime ( true ) * 1000 );
        $paramString .= '&ts=' . $ts;

        $paramString .= '&u=' . urlencode ( base64_encode ( $eduService->encryptWithRepoKey( $eduService->config->username ) ) );

        $toSign = $eduService->config->appId . $ts . $query['obj_id'];
        $signature = $eduService->helperBase->sign( $toSign );

        $paramString .= '&sig=' . urlencode ( $signature );
        $paramString .= '&signed=' . urlencode( $toSign );
        $paramString .= '&closeOnBack=true';

        $ticket = $eduService -> getTicket();

        $paramString .= '&ticket=' . urlencode( base64_encode( $eduService->encryptWithRepoKey( $ticket ) ) );

        $redirect_url .= $paramString;

        $this->getOutput()->redirect( $redirect_url );
    }

}