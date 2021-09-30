<?php

// autoloader: EduSharingService

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
        
        $usageid = $request->getVal('usageid');

        $eduSharingService = new EduSharingService();
        $postData = new stdClass ();

        $edu_sharing->contenturl = $eduSharingService->config->contentUrl;

        $postData->nodeId = str_replace("ccrep://local/","", $request->getVal('oid'));
        $postData->nodeVersion= null;
        $postData->containerId =  $request->getVal('pid');
        $postData->resourceId = $request->getVal('resid');
        $postData->usageId =  $usageid;

        $result = $eduSharingService ->getNode($postData);

        $html = $result["detailsSnippet"];
        $edu_sharing->mediatype = $result['node']['mediatype'];
        // take over output since we dont't want any stuff around our html
        $this->getOutput()->disable();

        print($this->display ( str_replace("width:0px;", "",  $html), $edu_sharing ));
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

    function display($html, $eduobj) {

        global $wgScriptPath;

        $html = str_replace ( array (
            "\r\n",
            "\r",
            "\n"
        ), '', $html );
        //$html = str_replace ( '\'', '\\\'', $html );

        /*
         * replaces {{{LMS_INLINE_HELPER_SCRIPT}}}
         * 
         */

        #var_dump($html);
        $html = str_replace("{{{LMS_INLINE_HELPER_SCRIPT}}}", SpecialPage::getTitleFor('EduInlineHelper')->getLocalUrl() . "&reUrl=".urlencode($this -> getRedirectUrl ($eduobj, 'window')), $html);

        /*
         * replaces <es:title ...>...</es:title>
         */
        $html = preg_replace ( "/<es:title[^>]*>.*<\/es:title>/Uims", $eduobj->printTitle, $html );
        $html = preg_replace ( '/(<a.* class="edu_sharing_filename".*>)(.*)(<\/a>)/Uims', "$1" . $eduobj->printTitle . "$3", $html );
        /*
         * For images, audio and video show a capture underneath object
         */
        
        // $mediatypes = array (
        //     'file-image',
        //     'file-video',
        //     'file-audio'
        // );
        // foreach ( $mediatypes as $mediatype ) {
        //     if (strpos ( $eduobj->mediatype, $mediatype ) !== false)
        //         $html .= '<div class="mw-edusharing-caption">' . $eduobj->printTitle . '</div>';
        // }

        // $html .= $eduobj->mediatype;

        return $html;
    }

    
}
