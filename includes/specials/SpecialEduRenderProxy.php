<?php
require_once (__DIR__ . '/../ESApp.php');

class SpecialEduRenderProxy extends SpecialPage {

    function __construct() {
		parent::__construct( 'EduRenderProxy', '', false );
	}

    public function execute( $par ) {

        $request = $this->getRequest();

        $edu_sharing = new stdClass ();

        $edu_sharing->id = $request->getVal('oid');
        $edu_sharing->appid = $request->getVal('appid');
        $edu_sharing->repid = $request->getVal('repid');
        $edu_sharing->resourceid = $request->getVal('resid');
        $edu_sharing->height = $request->getVal('height');
        $edu_sharing->width = $request->getVal('width');
        $edu_sharing->mimetype = $request->getVal('mime');
        $edu_sharing->pageid = $request->getVal('pid');
        $edu_sharing->printTitle = $request->getVal('printTitle');
        $edu_sharing->language = $request->getVal('language');
        
        $es = new ESApp ();
        $es->loadApps ();
        $conf = $es->getHomeConf ();
        $edu_sharing->contenturl = $conf->prop_array ['contenturl'];
        
        $url = $this->getRedirectUrl ( $edu_sharing, 'inline' );
        
        $url .= $this->getSecurityParams ( $conf, $edu_sharing->id , $es);
        $url .= "&version=0";
        
        $html = $this->getRenderHtml ( $url );
        
        // DEBUG
        //$html .= '<code>' . $url . '</code>';
        //$html .= $e->getRenderHtml ( $url );
        // DEBUG
        
        // take over output since we dont't want any stuff around our html
        $this->getOutput()->disable();
        print($this->display ( $html, $edu_sharing, $conf ));
    }

    public function getRedirectUrl($eduobj, $display_mode = 'inline') {

        if (empty ( $eduobj->contenturl )) {
            trigger_error ( 'No repository-content-url configured.' );
        }

        $url = $eduobj->contenturl;

        $app_id = $eduobj->appid;
        if (empty ( $app_id )) {
            trigger_error ( 'No application-app-id configured.', E_ERROR );
        }

        $url .= '?app_id=' . urlencode ( $app_id );

        $rep_id = $eduobj->repid;
        if (empty ( $rep_id )) {
            trigger_error ( 'No repository-app-id configured.', E_ERROR );
        }

        $url .= '&rep_id=' . urlencode ( $rep_id );

        $resourceReference = $eduobj->id;
        if (empty ( $resourceReference )) {
            trigger_error ( 'No object-id returned.' );
        }

        $url .= '&obj_id=' . urlencode ( $resourceReference );

        $url .= '&resource_id=' . urlencode ( $eduobj->resourceid );
        $url .= '&course_id=' . urlencode ( $eduobj->pageid );

        $url .= '&display=' . urlencode ( $display_mode );

        $url .= '&width=' . urlencode ( $eduobj->width );
        $url .= '&height=' . urlencode ( $eduobj->height );

        $url .= '&language=' . urlencode ( $eduobj->language );
        $url .= '&locale=' . urlencode ( $eduobj->language );

        return $url;
    }
    function getRenderHtml($url) {
        $inline = "";
        try {
            $curl_handle = curl_init ( $url );
            if (! $curl_handle) {
                throw new Exception ( 'Error initializing CURL.' );
            }

            curl_setopt ( $curl_handle, CURLOPT_FOLLOWLOCATION, 1 );
            curl_setopt ( $curl_handle, CURLOPT_HEADER, 0 );
            curl_setopt ( $curl_handle, CURLOPT_RETURNTRANSFER, 1 );
            curl_setopt ( $curl_handle, CURLOPT_USERAGENT, $_SERVER ['HTTP_USER_AGENT'] );
            curl_setopt ( $curl_handle, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt ( $curl_handle, CURLOPT_SSL_VERIFYHOST, false );

            $inline = curl_exec ( $curl_handle );
            curl_close ( $curl_handle );
        } catch ( Exception $e ) {
            error_log ( print_r ( $e, true ) );
            curl_close ( $curl_handle );
            return false;
        }

        return $inline;
    }


    function display($html, $eduobj, $conf) {

        global $wgScriptPath;

        $html = str_replace ( array (
            "\r\n",
            "\r",
            "\n"
        ), '', $html );
        //$html = str_replace ( '\'', '\\\'', $html );

        /*
         * replaces {{{LMS_INLINE_HELPER_SCRIPT}}}
         */
        $html = str_replace("{{{LMS_INLINE_HELPER_SCRIPT}}}", SpecialPage::getTitleFor('EduInlineHelper')->getLocalUrl() . "&reUrl=".urlencode($this -> getRedirectUrl ($eduobj, 'window')), $html);

        /*
         * replaces <es:title ...>...</es:title>
         */
        $html = preg_replace ( "/<es:title[^>]*>.*<\/es:title>/Uims", $eduobj->printTitle, $html );
        /*
         * For images, audio and video show a capture underneath object
         */
        $mimetypes = array (
            'image',
            'video',
            'audio'
        );
        foreach ( $mimetypes as $mimetype ) {
            if (strpos ( $eduobj->mimetype, $mimetype ) !== false)
                $html .= '<p class="caption">' . $eduobj->printTitle . '</p>';
        }

        return $html;
    }

    public function getSecurityParams($conf, $object_id, $es) {
        $paramString = '';

        $ts = round ( microtime ( true ) * 1000 );
        $paramString .= '&ts=' . $ts;
        
        $userid = trim(strtolower($this->getRequest()->getSession()->get('wsUserName')));
        if( empty($userid) || filter_var($userid, FILTER_VALIDATE_IP) !== false)
            $userid = 'mw_guest';

        $paramString .= '&u=' . urlencode ( base64_encode ( $es -> edusharing_encrypt_with_repo_public($userid)));

        $signature = '';
        $priv_key = $conf->prop_array ['private_key'];
     
        $pkeyid = openssl_get_privatekey ( $priv_key );
        openssl_sign ( $conf->prop_array ['appid'] . $ts . $object_id, $signature, $pkeyid );
        $signature = base64_encode ( $signature );
        openssl_free_key ( $pkeyid );
        $paramString .= '&sig=' . urlencode ( $signature );
        $paramString .= '&signed=' . urlencode($conf -> prop_array['appid'] . $ts . $object_id);

        return $paramString;
    }
}