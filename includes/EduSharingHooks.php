<?php
/**
 * Hooks for EduSharing extension
 *
 * @file
 * @ingroup Extensions
 */

/**
 * EduSharing hooks
 */

use MediaWiki\Revision\SlotRecord;

class EduSharingHooks {

    /**
     * Adds extra variables to the global config
     *  @param array &$vars
     */
    public static function onResourceLoaderGetConfigVars( array &$vars ) {
        #TODO: move static js config here from self::onMakeGlobalVariablesScript()
    }

    /**
     * Adds edu-sharing item to editor toolbar
     * 
     * @param $editPage
     * @param $output
     * @return true
     */
    public static function onMakeGlobalVariablesScript( array &$vars, OutputPage $output ) {
        
        $eduService = new EduSharingService();
        $ticket = $eduService -> getTicket();

        global $wgServer, $wgScriptPath;

        $output -> addModules('ext.eduSharing.dialog');
        $output -> addJsConfigVars( [ 'eduticket' => $ticket ] );
        $output -> addJsConfigVars( [ 'eduusername' => $eduService->config->username ] );
        $output -> addJsConfigVars( [ 'eduappid' => $eduService->config->appId ] );
        $output -> addJsConfigVars( [ 'edugui' => $eduService->config->baseUrl . '/components/search?ticket=' . $ticket . '&reurl=WINDOW' ] );

        $output -> addJsConfigVars( [ 'edu_preview_icon_video' => $eduService->config->iconMimeVideo ] );
        $output -> addJsConfigVars( [ 'edu_preview_icon_audio' => $eduService->config->iconMimeAudio ] );
        $output -> addJsConfigVars( [ 'edupreview' => $eduService->config->baseUrl . '/preview?' ] );
        $output -> addJsConfigVars( [ 'eduicon' => $wgServer . $wgScriptPath . '/extensions/EduSharing/resources/images/edu-icon.svg' ] );
    }


    /**
     * Parses $xml for edutags
     * @param string $tag 
     * @param string $xml
     * @return array $matches
     */
    public static function get_edutags($tag, $xml) {
        $tag = preg_quote($tag);
        preg_match_all('#<' . $tag . '([^>]*)>(.*)</' . $tag . '>#Umsi', $xml, $matches, PREG_PATTERN_ORDER);

        return $matches[0];
    }


    private static function deleteResourceAndUsage( $resource ) {

        /*
        * Delete record in db
        */
        $dbw = wfGetDB( DB_PRIMARY );
        $dbw -> delete('edusharing_resource', array( 'EDUSHARING_RESOURCE_ID = ' . $resource->EDUSHARING_RESOURCE_ID ), $fname = 'Database::delete');

        $postData           = new stdClass ();
        $postData->nodeId   = str_replace("ccrep://local/","",$resource->EDUSHARING_RESOURCE_OBJECT_URL);
        $postData->usageId  = $resource->EDUSHARING_RESOURCE_USAGE;

        // delete usage from repo
        $eduService = new EduSharingService();
        $eduService -> deleteUsage( $postData );
    }


    private static function addResourceAndUsage( $resourceData, bool $isRestore = false ) {
        
        // if we don'T restore a previously deleted resource, we don'T want to re-use an existing id
        if ( $isRestore !== true ) {
            unset( $resourceData[ 'EDUSHARING_RESOURCE_ID' ] );
        }

        $dbw = wfGetDB( DB_PRIMARY );
        $dbw -> insert('edusharing_resource', $resourceData, 'Database::insert');
        $resourceId = $dbw -> insertId();

        $eduService = new EduSharingService();
        $postData   = new stdClass ();

        $postData->ticket       = $eduService->getTicket();
        $postData->containerId  = $resourceData[ 'EDUSHARING_RESOURCE_PAGE_ID' ];
        $postData->resourceId   = $resourceId;
        $postData->nodeId       = str_replace( "ccrep://local/", "", $resourceData[ 'EDUSHARING_RESOURCE_OBJECT_URL' ] );

        $usage = $eduService -> createUsage( $postData );

        if ( $usage ) {
            $dbw->update( 'edusharing_resource', [ 'EDUSHARING_RESOURCE_USAGE' => $usage->usageId ], ['EDUSHARING_RESOURCE_ID' => $resourceId ], 'Database::update' );
        }
        
        return $usage;
    }


    /**
     * Deletes usages for edu-sharing resources on article delete
     * @param &$article
     * @param &$user
     * @param &$reason
     * @param &$error
     * @return true
     */
    public static function onArticleDelete( &$article, &$user, &$reason, &$error ) {
        
        /*
         * Select edu-sharing resources of the article that will be deleted
         */
        $dbr = wfGetDB( DB_REPLICA );
        $res = $dbr -> select('edusharing_resource',
            array( 'EDUSHARING_RESOURCE_ID', 'EDUSHARING_RESOURCE_USAGE','EDUSHARING_RESOURCE_OBJECT_URL' ), // $vars (columns of the table)
            'EDUSHARING_RESOURCE_PAGE_ID = ' . $article -> getId(),
            'Database::select',
            array('ORDER BY' => 'EDUSHARING_RESOURCE_ID ASC')
        );
        
        /*
         * Delete usages for edusharing resources 
         */
        foreach($res as $resource) {    
            self::deleteResourceAndUsage( $resource );
        }

        return true;
    }
    

    /**
     * Adds usages for edu-sharing resources on article undelete
     * @param $title
     * @param $create
     * @param $comment
     * @param $oldPageId
     * @param $restoredPages
     * 
     * @return true
     */
    public static function onArticleUndelete( Title $title, $create, $comment, $oldPageId, $restoredPages ) {
        
        // get article content, we have to parse the wikitext since we have probably deleted the resource registration before
        $wikiPage = WikiPage::factory( $title );
        $text = $wikiPage->getRevisionRecord()->getContent( SlotRecord::MAIN )->getText();

        self::syncArticleResources( $title, $oldPageId, $text, true );
        
        return true;
    }

    /*
    * parse articel text for edusharing tags and look for corresponding resource entries in the database.
    * if no matching entry is found, it is created. 
    *
    * if we are in article restore context, we use the existing resourceId from the tag to write the database record, 
    * otherwise we create a new onde and insert it into the tag.
    */
    private static function syncArticleResources( $title, $pageId, string $text, bool $isRestore ) {

        /*
         * Select all article's resources
         */
        $dbr = wfGetDB( DB_REPLICA );
        $res = $dbr->select('edusharing_resource', 
            array( 'EDUSHARING_RESOURCE_ID', 'EDUSHARING_RESOURCE_USAGE','EDUSHARING_RESOURCE_OBJECT_URL' ), // $vars (columns of the table)
            'EDUSHARING_RESOURCE_PAGE_ID = ' . $pageId, // $conds
            'Database::select', // $fname = 'Database::select',
            array('ORDER BY' => 'EDUSHARING_RESOURCE_ID ASC') // $options = array()
        );

        $old_list = array();
        foreach ($res as $row) {
            $old_list[$row -> EDUSHARING_RESOURCE_ID] = $row;
        }

        /*
         * Get edu-sharing tags from $text 
         */
        $matches = self::get_edutags('edusharing', $text);

        /*
         * For each resource found in text 
         */
        foreach ($matches as $edutag) {            
            $Response   = simplexml_load_string($edutag);

            $resourceData = array(
                'EDUSHARING_RESOURCE_ID' => (string)$Response['resourceid'],
                'EDUSHARING_RESOURCE_PAGE_ID' => $pageId, 
                'EDUSHARING_RESOURCE_OBJECT_URL' => (string)$Response['id'],
                'EDUSHARING_RESOURCE_TITLE' => $title, 
                'EDUSHARING_RESOURCE_WIDTH' => (string)$Response['width'], 
                'EDUSHARING_RESOURCE_HEIGHT' => (string)$Response['height'], 
                'EDUSHARING_RESOURCE_FLOAT' => (string)$Response['float']
            );

            /*
             * For new resources insert db record and set usage, mark as processed
             */
            if ($Response['action'] == 'new') {

                $usage = self::addResourceAndUsage( $resourceData, $isRestore );

                $Response -> addAttribute( 'resourceid', $usage->resourceId );
                $Response['action'] = 'processed';          
                
            } else if ($Response['action'] == 'processed') {               
                                
                /*
                 * Try to get record for this resource with select conditions article id and resource id.
                 * If no record can be found this resource must be copied from another page. So add new record and add usage.
                 */
                $dbr = wfGetDB( DB_REPLICA );
                $res = $dbr -> select('edusharing_resource',
                    array('EDUSHARING_RESOURCE_ID', 'EDUSHARING_RESOURCE_PAGE_ID', 'EDUSHARING_RESOURCE_USAGE'),
                    array('EDUSHARING_RESOURCE_PAGE_ID = ' . $pageId, 'EDUSHARING_RESOURCE_ID = ' . $Response['resourceid']));
                
                $resCount = 0;
                foreach($res as $r) {
                    $resCount++;
                }
                
                /*
                 * If record exists unset resource from deletion list
                 */
                if($resCount > 0) {
                    
                    $_resourceid = (int)$Response['resourceid'];
                    unset($old_list[$_resourceid]);

                } else {
                                        
                    $usage = self::addResourceAndUsage( $resourceData, $isRestore );

                    $Response['resourceid'] = $usage->resourceId;
                }
            }

            /*
            * Write properties to text
            */
            $_tag = html_entity_decode(str_replace('<?xml version="1.0"?>', '', $Response -> asXML()));
            $text = str_replace($edutag, $_tag, $text);      
            
        }

        /*
         * Delete resources that have been removed from article
         */
        foreach ($old_list as $item) {           
            /*
             * Delete usage
             */
           	self::deleteResourceAndUsage( $item );
        }

        return $text;

    }

    
    /**
     * Adds/removes resources and usages when article is saved
     * 
     * @param $parser
     * @param &$text
     * @return true
     */

     public static function onParserPreSaveTransformComplete( $parser, &$text ) {
        $user = $parser->getUser();
        $title = $parser->getTitle();

        // check if called from the "right" context, i.e. while saving a normal wikipage
        // to prevent exception when running in visual editor context
        if ( $title->getNamespace() == -1 )
            return true;
        $wikiPage   = WikiPage::factory( $title );
        $pageId     = $wikiPage -> getId();
        
        $text = self::syncArticleResources( $title, $pageId, $text, false );

        return true;
    }

    /**
     * Adds hook to parser that handles edu-sharing tags
     * 
     * @param $parser
     * @return true
     */
    public static function wfEdusharingExtensionInit(Parser $parser) {

        $parser -> setHook("edusharing", array( __CLASS__, 'wfEduSharingRender' ));
        return true;
    }

    /**
     * The callback function for converting the input text to HTML output
     * Handles page view as well as page preview
     * 
     * @param $input
     * @param $args
     * @param $parser
     * @param $frame
     * @return string
     */
    public static function wfEduSharingRender($input, array $args, Parser $parser, PPFrame $frame) { 
                
        /*
         * Set edu-sharing properties, params for proxy request
         * Render wrapper
         * 
         * $args['action'] === 'processed' - page view
         * $_GET['action'] == 'submit' - preview
         */
        if (isset($args['action']) && ($args['action'] === 'processed') || $_GET['action'] == 'submit') {

            // get usageId from database
            $dbr = wfGetDB( DB_REPLICA );
            $res = $dbr -> selectRow('edusharing_resource',
                array( 'EDUSHARING_RESOURCE_ID', 'EDUSHARING_RESOURCE_USAGE','EDUSHARING_RESOURCE_OBJECT_URL' ), // $vars (columns of the table)
                'EDUSHARING_RESOURCE_ID = ' . $args['resourceid'],
                'Database::select',
                array('ORDER BY' => 'EDUSHARING_RESOURCE_ID ASC')
            );

            $usageId = $res->EDUSHARING_RESOURCE_USAGE;

            global $wgServer, $wgScriptPath;
            $eduService = new EduSharingService();

            $edu_sharing = new stdClass();

            $edu_sharing -> id = $args['id'];
            $eduObject = parse_url($edu_sharing -> id);
            $edu_sharing -> id = str_replace('/', '', $eduObject['path']);
            $edu_sharing -> appid = $eduService->config->appId;
            $edu_sharing -> repid = $eduObject['host'];           
            $edu_sharing -> resourceid = $args['resourceid'];
            $edu_sharing -> height = $args['height'];
            $edu_sharing -> width = $args['width'];
            $edu_sharing -> mimetype = $args['mimetype'];
            $edu_sharing -> page = $parser->mTitle->mArticleID;
            $edu_sharing -> usageid = ( $usageId !== null ) ? $usageId : "";

            if(!empty($args['float'])){
            	 $edu_sharing -> float = $args['float'];
            } else {
            	 $edu_sharing -> float = 'none';
            }

            $param = '&oid=' . $edu_sharing -> id;
            $param .= '&resid=' . $edu_sharing -> resourceid;
            $param .= '&usageid=' .  $edu_sharing -> usageid;
            $param .= '&height=' . $edu_sharing -> height;
            $param .= '&width=' . $edu_sharing -> width;
            $param .= '&mime=' . $edu_sharing -> mimetype;
            $param .= '&pid=' . $edu_sharing -> page;
            $param .= '&appid=' . $edu_sharing -> appid;
            $param .= '&repid=' . $edu_sharing -> repid;
            $param .= '&printTitle=' . addslashes($input);
            $param .= '&language=' . $eduService->config->user->mOptions['language'];

            $dataUrl = SpecialPage::getTitleFor('EduRenderProxy')->getLocalUrl() . $param;

            switch($edu_sharing -> float) {
            //     case 'left': $style = "float: left; display: block; margin: 10px 10px 10px 0;"; break;
            //     case 'none': $style = "float: none; display: block; margin: 10px 0;"; break;
            //     case 'center': $style = "float: none; display: block; margin: 10px auto; border: 5px solid red"; break;
            //     case 'right': $style = "float: right; display: block; margin: 10px 0 10px 10px;"; break;
            //     case 'inline':
            //     default: $style = 'float: none; display: inline-block; margin: 0';

                case 'left': $classes = "tleft"; break;
                case 'none': $classes = "tnone center"; break;
                case 'center': $classes = "tnone center"; break;
                case 'right': $classes = "tright"; break;
                case 'inline':
                default: $classes = "tnone center"; break;
            }

            if(isset($args['action']) && ($args['action'] === 'processed')) {
                $wrapperWidth = 'style="max-width: 100%; width: ' . $edu_sharing -> width . 'px;"';                   
                //$wrapperStyle = 'style="height: ' . $edu_sharing -> height . 'px; width:' . $edu_sharing -> width . 'px; ' . $style . '"';                   
                $text = '<div class="mw-edusharing-container ' . $classes . '" ' . $wrapperWidth . '><div class="thumbinner"><div class="edu_wrapper" id="content_wrapper' . $edu_sharing -> id . '-' . $edu_sharing -> resourceid . '" ' . $wrapperWidth . '><div data-type="esObject" data-url="'.$dataUrl.'" class="spinnerContainer"><div class="inner"><div class="spinner1"></div></div><div class="inner"><div class="spinner2"></div></div><div class="inner"><div class="spinner3"></div></div></div></div></div></div>';
            } else {    
                $text = self::getPreview($edu_sharing, $input, $style);
            }
            
            return $text;

        } else {

            return 'Unknown edusharing action: "' . $args['action'] . '"';

        }

    }

    /**
     * Add module 'ext.eduSharing.display' providing js loadScript function
     * @param &$out
     * @param &$skin
     * @return true
     * 
     */
    public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
        global $wgOut;
        $wgOut->addModules( 'ext.eduSharing.display' );
        $wgOut->addModules( 'ext.eduSharing.visualEditor' );
        return true;
    }


    /**
     * Adds table 'edusharing_resource' to wiki db
     * @param $updater
     * @return true
     * 
     */
    public static function createEdusharingDatabase( DatabaseUpdater $updater ) {
        $updater -> addExtensionTable('edusharing_resource', __DIR__ . '/../sql/EduSharing.sql', true);
        return true;
    }

    /**
     * Adds field "usageid to existing "table 'edusharing_resource' when updating
     * @param $updater
     * @return true
     * 
     */
    public static function updateEdusharingDatabase( DatabaseUpdater $updater ) {
        $updater -> addExtensionField('edusharing_resource', 'EDUSHARING_RESOURCE_USAGE', __DIR__ . '/../sql/EduSharingAddUsageField.sql', true);
        $updater -> addExtensionIndex('edusharing_resource', 'id_usage', __DIR__ . '/../sql/EduSharingAddUsageIndex.sql', true);
        return true;
    }


}
?>
