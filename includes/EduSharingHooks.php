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
namespace MediaWiki\Extension\EduSharing;

use MediaWiki\Revision\SlotRecord;
use MediaWiki\Config\Config;
use MediaWiki\Content\TextContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Page\PageReference;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\SpecialPage\SpecialPage;
use ManualLogEntry;
use Parser;
use StatusValue;
use MediaWiki\Content\ContentHandler;

class EduSharingHooks implements
    \MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook,
    \MediaWiki\Output\Hook\MakeGlobalVariablesScriptHook,
    \MediaWiki\Hook\ParserFirstCallInitHook,
    \MediaWiki\Hook\ParserPreSaveTransformCompleteHook,
    \MediaWiki\Page\Hook\PageDeleteHook,
    \MediaWiki\Page\Hook\PageUndeleteCompleteHook,
    \MediaWiki\Output\Hook\BeforePageDisplayHook,
    \MediaWiki\Storage\Hook\PageSaveCompleteHook
{

    /** @var array<string,int[]>  pageKey => [resourceId, ...] */
    private static array $pendingResourceIds = [];

    /** @inheritDoc */
    public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
        #TODO: move static js config here from self::onMakeGlobalVariablesScript()
    }

    /**
     * Adds edu-sharing item to editor toolbar
     */
    /** @inheritDoc */
    public function onMakeGlobalVariablesScript( &$vars, $out ): void {
        
        $user    = $out->getUser();
        $services = MediaWikiServices::getInstance();
        $mwConfig = $services->getConfigFactory()->makeConfig( 'edusharing' );

        $eduService = new EduSharingService( $user, $mwConfig );
        $ticket = $eduService->getTicket();

        global $wgServer, $wgScriptPath;

        $out -> addModules('ext.eduSharing.dialog');
        $out -> addJsConfigVars( [ 'eduticket' => $ticket ] );
        $out -> addJsConfigVars( [ 'eduusername' => $eduService->config->username ] );
        $out -> addJsConfigVars( [ 'eduappid' => $eduService->config->appId ] );
        $out -> addJsConfigVars( [ 'edugui' => $eduService->config->baseUrl . '/components/search?ticket=' . $ticket . '&reurl=WINDOW' ] );

        $out -> addJsConfigVars( [ 'edu_preview_icon_video' => $eduService->config->iconMimeVideo ] );
        $out -> addJsConfigVars( [ 'edu_preview_icon_audio' => $eduService->config->iconMimeAudio ] );
        $out -> addJsConfigVars( [ 'edupreview' => $eduService->config->baseUrl . '/preview?' ] );
        $out -> addJsConfigVars( [ 'eduicon' => $wgServer . $wgScriptPath . '/extensions/EduSharing/resources/images/edu-icon.svg' ] );
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


    private static function deleteResourceAndUsage( EduSharingService $eduService, $resource ) {

        /*
        * Delete record in db
        */
        $dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
        $dbw = $dbProvider->getPrimaryDatabase();

        $dbw -> delete('edusharing_resource', array( 'EDUSHARING_RESOURCE_ID = ' . $resource->EDUSHARING_RESOURCE_ID ), $fname = 'Database::delete');

        $postData           = new \stdClass ();
        $postData->nodeId   = str_replace("ccrep://local/","",$resource->EDUSHARING_RESOURCE_OBJECT_URL);
        $postData->usageId  = $resource->EDUSHARING_RESOURCE_USAGE;

        // delete usage from repo
        $eduService -> deleteUsage( $postData );
    }


    private static function addResourceAndUsage( EduSharingService $eduService, $resourceData, bool $isRestore = false ) {
        
        // if we don't restore a previously deleted resource, we don't want to re-use an existing id
        if ( $isRestore !== true ) {
            unset( $resourceData[ 'EDUSHARING_RESOURCE_ID' ] );
        }

        $dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
        $dbw = $dbProvider->getPrimaryDatabase();
        
        $dbw -> insert('edusharing_resource', $resourceData, 'Database::insert');
        $resourceId = $dbw->insertId();

        $postData   = new \stdClass ();

        $postData->ticket       = $eduService->getTicket();
        $postData->containerId  = ( $resourceData[ 'EDUSHARING_RESOURCE_PAGE_ID' ] === NULL ? 0 : $resourceData[ 'EDUSHARING_RESOURCE_PAGE_ID' ] );
        $postData->resourceId   = $resourceId;
        $postData->nodeId       = str_replace( "ccrep://local/", "", $resourceData[ 'EDUSHARING_RESOURCE_OBJECT_URL' ] );

        $usage = $eduService->createUsage( $postData );

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
    public function onPageDelete( ProperPageIdentity $page, Authority $deleter, string $reason, StatusValue $status, bool $suppress ) {
        
        /*
         * Select edu-sharing resources of the article that will be deleted
         */
        $dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
        $dbr = $dbProvider->getReplicaDatabase();
        
        $res = $dbr -> select('edusharing_resource',
            array( 'EDUSHARING_RESOURCE_ID', 'EDUSHARING_RESOURCE_USAGE','EDUSHARING_RESOURCE_OBJECT_URL' ), // $vars (columns of the table)
            'EDUSHARING_RESOURCE_PAGE_ID = ' . $page -> getId(),
            'Database::select',
            array('ORDER BY' => 'EDUSHARING_RESOURCE_ID ASC')
        );
        
        /*
         * Delete usages for edusharing resources 
         */
        $user = $deleter->getUser(); 
        $services = MediaWikiServices::getInstance();
        $mwConfig = $services->getConfigFactory()->makeConfig( 'edusharing' );

        $eduService = new EduSharingService( $user, $mwConfig );        
        foreach($res as $resource) {    
            self::deleteResourceAndUsage( $eduService, $resource );
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
    public function onPageUndeleteComplete( ProperPageIdentity $pageIdentity, Authority $restorer, string $reason, RevisionRecord $restoredRev, ManualLogEntry $logEntry, int $restoredRevisionCount, bool $created, array $restoredPageIds ): void {
        
        $content = $restoredRev->getContent( SlotRecord::MAIN );
        if ( !$content ) {
            error_log( 'No content found for page ' . $pageIdentity->__toString() . " with revision: " . $restoredRev->getId() ); 
            return;
        }

        if ( $content instanceof TextContent ) {
            $text = $content->getText();
        } else {
            error_log( 'No Text Content for page ' . $pageIdentity->__toString() . " with revision: " . $restoredRev->getId() ); 
            return;
        }

        $user = $restorer->getUser(); 
        $services = MediaWikiServices::getInstance();
        $mwConfig = $services->getConfigFactory()->makeConfig( 'edusharing' );

        $eduService = new EduSharingService( $user, $mwConfig );  
        self::syncArticleResources( $eduService, $pageIdentity, $text, true );
        
    }

    /*
    * parse articel text for edusharing tags and look for corresponding resource entries in the database.
    * if no matching entry is found, it is created. 
    *
    * if we are in article restore context, we use the existing resourceId from the tag to write the database record, 
    * otherwise we create a new onde and insert it into the tag.
    */
    private static function syncArticleResources( EduSharingService $eduService, PageReference|ProperPageIdentity $pageRef, string &$text, bool $isRestore ): array {
        $resourceIds = [];
        $old_list = [];
        $pageId = NULL;
        /*
         * Select all article's resources
         */
        $dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
        $dbr = $dbProvider->getReplicaDatabase();

        if ( $pageRef instanceof ProperPageIdentity ) {
            // if we have a PageIdentity object, we are in undelete context and have a pageId
            $pageId = $pageRef->getId();
        } else {
            // otherwise we come from creating/editing a page and may or may not have a pageId
            // to check we create a title object from the PageReference and and have a look at its articleId. 
            // If 0, the page is new and we don't have a pageId and can't use it right now
            $title = MediaWikiServices::getInstance()
                ->getTitleFactory()
                ->newFromPageReference( $pageRef );

            $articleId = $title->getArticleID();
            $pageId = ( $articleId > 0 ? $articleId : NULL );
        }
        if ( $pageId !== NULL ) {
            $res = $dbr->select('edusharing_resource', 
                array( 'EDUSHARING_RESOURCE_ID', 'EDUSHARING_RESOURCE_USAGE','EDUSHARING_RESOURCE_OBJECT_URL' ), // $vars (columns of the table)
                'EDUSHARING_RESOURCE_PAGE_ID = ' . $pageId, // $conds
                'Database::select', // $fname = 'Database::select',
                array('ORDER BY' => 'EDUSHARING_RESOURCE_ID ASC') // $options = array()
            );

            foreach ($res as $row) {
                $old_list[$row->EDUSHARING_RESOURCE_ID] = $row;
            }
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
                'EDUSHARING_RESOURCE_TITLE' => $pageRef->getDBkey(), 
                'EDUSHARING_RESOURCE_WIDTH' => (string)$Response['width'], 
                'EDUSHARING_RESOURCE_HEIGHT' => (string)$Response['height'], 
                'EDUSHARING_RESOURCE_FLOAT' => (string)$Response['float']
            );

            /*
             * For new resources insert db record and set usage, mark as processed
             */
            if ($Response['action'] == 'new') {

                $usage = self::addResourceAndUsage( $eduService, $resourceData, $isRestore );
                // if we don't have a pageId (b/c page is new and not yet saved) we need to save the resourceIds
                // and add the pageId to the resource record in the PageSaveCompleteHook
                if ( $pageId === NULL ) {
                    $resourceIds[] = $usage->resourceId;
                }
                $Response -> addAttribute( 'resourceid', $usage->resourceId );
                $Response['action'] = 'processed';          
                
            } else if ($Response['action'] == 'processed') {               
                /*
                 * Try to get record for this resource with select conditions article id and resource id.
                 * If no record can be found this resource must be copied from another page. So add new record and add usage.
                 */
                
                $dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
                $dbr = $dbProvider->getReplicaDatabase();

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
                if( $resCount > 0 ) {
                    
                    $_resourceid = (int)$Response['resourceid'];
                    unset($old_list[$_resourceid]);

                } else {
                                        
                    $usage = self::addResourceAndUsage( $eduService, $resourceData, $isRestore );

                    $Response['resourceid'] = $usage->resourceId;
                    // if we don't have a pageId (b/c page is new and not yet saved) we need to save the resourceIds
                    // and add the pageId to the resource record in the PageSaveCompleteHook
                    if ( $pageId === NULL ) {
                        $resourceIds[] = $usage->resourceId;
                    }
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
           	self::deleteResourceAndUsage( $eduService, $item );
        }

        return $resourceIds;

    }

    
    /**
     * Adds/removes resources and usages when article is saved
     * 
     * @param $parser
     * @param &$text
     */

     public function onParserPreSaveTransformComplete( $parser, &$text ) {
        $pageRef = $parser->getPage();

        // check if called from the "right" context, i.e. while saving a normal wikipage
        // to prevent exception when running in visual editor context
        if ( !$pageRef || $pageRef->getNamespace() === NS_SPECIAL ) {
            return true;
        }

        $user    = $parser->getUserIdentity();
        $services = MediaWikiServices::getInstance();
        $mwConfig = $services->getConfigFactory()->makeConfig( 'edusharing' );

        $eduService = new EduSharingService( $user, $mwConfig );

        $resourceIds = self::syncArticleResources( $eduService, $pageRef, $text, false );

        // save resourceIds with missing pageId to be completed in PageSaveCompleteHook
        if ( $resourceIds ) {
            $pageKey = $pageRef->getNamespace() . ':' . $pageRef->getDBkey();
            if ( !isset( self::$pendingResourceIds[$pageKey] ) ) {
                self::$pendingResourceIds[$pageKey] = [];
            }
            self::$pendingResourceIds[$pageKey] = array_merge(
                self::$pendingResourceIds[$pageKey],
                $resourceIds
            );
        }
        return true;
    }

    /**
     * Adds pageId reference to freshly created resources where missing
     * 
     */    
    public function onPageSaveComplete( $wikiPage, $user, $summary, $flags, $revisionRecord, $editResult ) {
        $pageId = $wikiPage->getId();
        $title  = $wikiPage->getTitle();
        $pageKey = $wikiPage->getNamespace() . ':' . $wikiPage->getDBkey();

        if ( empty( self::$pendingResourceIds[$pageKey] ) ) {
            return;
        }

        $resourceIds = self::$pendingResourceIds[$pageKey];

        $dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
        $dbw = $dbProvider->getPrimaryDatabase();

        foreach ( $resourceIds as $resId ) {
            $dbw->update(
                'edusharing_resource',
                [
                    'EDUSHARING_RESOURCE_PAGE_ID'   => $pageId,
                    'EDUSHARING_RESOURCE_TITLE'     => $title->getPrefixedText(),
                ],
                [
                    'EDUSHARING_RESOURCE_ID'      => $resId,
                    'EDUSHARING_RESOURCE_PAGE_ID' => null,
                ],
                __METHOD__
            );
        }

        // Optional: aufräumen
        unset( self::$pendingResourceIds[$pageKey] );
    }

    /**
     * Adds hook to parser that handles edu-sharing tags
     * 
     * @param $parser
     * @return true
     */
    public function onParserFirstCallInit( $parser ) {

        $parser->setHook( 'edusharing', [ self::class, 'wfEduSharingRender' ] );
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
    public static function wfEduSharingRender($input, array $args, Parser $parser, \PPFrame $frame) { 
                
        /*
         * Set edu-sharing properties, params for proxy request
         * Render wrapper
         * 
         * $args['action'] === 'processed' - page view
         * $_GET['action'] == 'submit' - preview
         */
        if (isset($args['action']) && ($args['action'] === 'processed') || $_GET['action'] == 'submit') {

            // get usageId from database
            $dbProvider = MediaWikiServices::getInstance()->getConnectionProvider();
            $dbr = $dbProvider->getReplicaDatabase();

            $res = $dbr -> selectRow('edusharing_resource',
                array( 'EDUSHARING_RESOURCE_ID', 'EDUSHARING_RESOURCE_USAGE','EDUSHARING_RESOURCE_OBJECT_URL' ), // $vars (columns of the table)
                'EDUSHARING_RESOURCE_ID = ' . $args['resourceid'],
                'Database::select',
                array('ORDER BY' => 'EDUSHARING_RESOURCE_ID ASC')
            );
            if ( $res ) {
                $usageId = $res->EDUSHARING_RESOURCE_USAGE;
            } else {
                return 'No resource record found, please try and save the page again.';
            }

            global $wgServer, $wgScriptPath;
            
            $user    = $parser->getUserIdentity();
            $services = MediaWikiServices::getInstance();
            $mwConfig = $services->getConfigFactory()->makeConfig( 'edusharing' );

            $eduService = new EduSharingService( $user, $mwConfig );

            $edu_sharing = new \stdClass();

            $edu_sharing -> id = $args['id'];
            $eduObject = parse_url($edu_sharing -> id);
            $edu_sharing -> id = str_replace('/', '', $eduObject['path']);
            $edu_sharing -> appid = $eduService->config->appId;
            $edu_sharing -> repid = $eduObject['host'];           
            $edu_sharing -> resourceid = $args['resourceid'];
            $edu_sharing -> height = $args['height'];
            $edu_sharing -> width = $args['width'];
            $edu_sharing -> mimetype = $args['mimetype'];
            $edu_sharing -> page = $parser->getTitle()->getArticleID();
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
            $param .= '&language=' . MediaWikiServices::getInstance()->getUserOptionsLookup()->getOption( $eduService->config->user, 'language' );

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
                // TODO: figure out what to do here and why
                //$text = self::getPreview($edu_sharing, $input, $style);
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
     * 
     */
    public function onBeforePageDisplay( $out, $skin ):void {
        global $wgOut;
        $wgOut->addModules( 'ext.eduSharing.display' );
        $wgOut->addModules( 'ext.eduSharing.visualEditor' );
    }

}
?>
