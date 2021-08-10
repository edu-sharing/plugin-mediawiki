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
     * Home configuration
     * var array
     */
    private static $hc;
        
    /**
     * Ticket from repository
     * var string
     */
    private static $ticket;

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

    /**
     * Deletes usages for edu-sharing resources on article delete
     * @param &$article
     * @param &$user
     * @param &$reason
     * @param &$error
     * @return true
     */
    public static function onArticleDelete(&$article, &$user, &$reason, &$error) {
        
        /*
         * Get db access
         */  
        $dbr = wfGetDB( DB_REPLICA );
        
        /*
         * Select edu-sharing resources of the article that will be deleted
         */
        $res = $dbr -> select('edusharing_resource',
            array('EDUSHARING_RESOURCE_ID', 'EDUSHARING_RESOURCE_PAGE_ID', 'EDUSHARING_RESOURCE_TITLE', 'EDUSHARING_RESOURCE_OBJECT_URL', 'EDUSHARING_RESOURCE_OBJECT_VERSION', 'EDUSHARING_RESOURCE_WIDTH', 'EDUSHARING_RESOURCE_HEIGHT', 'EDUSHARING_RESOURCE_FLOAT'), // $vars (columns of the table)
            'EDUSHARING_RESOURCE_PAGE_ID = ' . $article -> getId(),
            'Database::select',
            array('ORDER BY' => 'EDUSHARING_RESOURCE_ID ASC')
        );
        
        $eduws = new EduSharingWS();
        $hc = $eduws -> getHomeConfig();
        
        /*
         * Delte usages for edusharing resources 
         */
        foreach($res as $resource) {
          
            //usage2
            $params = array(
            		'eduRef' => $resource -> EDUSHARING_RESOURCE_OBJECT_URL,
            		'user' => strtolower($user -> getName()),
            		'lmsId' => $hc['appid'],
            		'courseId' => $article -> getId(),
            		'resourceId' => $resource -> EDUSHARING_RESOURCE_ID
            );
            
            $eduws -> delUsage($params);
        }        
        return true;
    }
    
    /**
     * Adds usages for edu-sharing resources on article undelete
     * @param &$title
     * @param &$create
     * @return true
     */
    public static function onArticleUndelete($title, $create) {
        
        global $wgUser;
        
        /*
         * Get db access 
         */
        $dbr = wfGetDB(DB_REPLICA);
        
        /*
         * Select all edu-sharing resources of this article.
         * Select condition is article text, because article gets a new id and the old one is not available anymore
         */
        $res = $dbr -> select('edusharing_resource',
            array('EDUSHARING_RESOURCE_ID', 'EDUSHARING_RESOURCE_PAGE_ID', 'EDUSHARING_RESOURCE_TITLE', 'EDUSHARING_RESOURCE_OBJECT_URL', 'EDUSHARING_RESOURCE_OBJECT_VERSION', 'EDUSHARING_RESOURCE_WIDTH', 'EDUSHARING_RESOURCE_HEIGHT', 'EDUSHARING_RESOURCE_FLOAT'), // $vars (columns of the table)
            'EDUSHARING_RESOURCE_TITLE = "' . $title->mTextform.'"',
            'Database::select'
        );
        
        $eduws = new EduSharingWS();
        $hc = $eduws -> getHomeConfig();
        
        /*
         * For each resource add usage
         */
        foreach($res as $resource) {
            $dbr = wfGetDB(DB_PRIMARY);
            $dbr -> update('edusharing_resource',
            array('EDUSHARING_RESOURCE_PAGE_ID' => $title -> mArticleID),
            array('EDUSHARING_RESOURCE_TITLE = "' . $title -> mTextform.'"'),
            'Database::update'
            );
            
            $edu_sharing -> id = $resource -> EDUSHARING_RESOURCE_OBJECT_URL;
            $edu_sharing -> repid = parse_url($resource -> EDUSHARING_RESOURCE_OBJECT_URL, PHP_URL_HOST);
            $edu_sharing -> height = $resource -> EDUSHARING_RESOURCE_HEIGHT;
            $edu_sharing -> width = $resource -> EDUSHARING_RESOURCE_WIDTH;
            $edu_sharing -> pageid = $title -> mArticleID;
            $edu_sharing -> ticket = RequestContext::getMain()->getRequest()->getSession()->get('repository_ticket');
            $edu_sharing -> user = strtolower($wgUser -> getName());
            $edu_sharing -> float = $resource -> EDUSHARING_RESOURCE_FLOAT;
            $edu_sharing -> resourceid = $resource -> EDUSHARING_RESOURCE_ID;
            $edu_sharing -> appid = $hc['appid'];

            $eduws -> addUsage($edu_sharing);
            
        }
        return true;
    }


/*
 * Foreach ES resource in this newly inserted article update record and usage with articleId (courseId in usage)
 * 
 * @param &$article
 * @param &$user
 * @param &$text
 * @param &$summary
 * @param $minoredit
 * @param $watchthis
 * @param $sectionanchor
 * @param &$flags
 * @param $revision
 * @return true
 */
public static function onArticleInsertComplete( &$article, &$user, $text, $summary, $minoredit, $watchthis, $sectionanchor, &$flags, $revision ) {
    global $wgUser;
    
    $eduws = new EduSharingWS();
    $hc = $eduws -> getHomeConfig();
    
    /*
     * Get edu-sharing tags from $text 
     */
    $matches = self::get_edutags('edusharing', $text);
        /*
         * For each resource found in text 
         */
        foreach ($matches as $edutag) {            
            $Response = simplexml_load_string($edutag);

            $_resourceId = (string)$Response['resourceid'];
            $_id = (string)$Response['id'];
            $_width = (string)$Response['width'];
            $_height = (string)$Response['height'];
            $_mimetype = (string)$Response['mimetype'];
            $_float = (string)$Response['float'];
            $_version = (string)$Response['version'];
            $_versionShow = (string)$Response['versionShow'];

            $dbr = wfGetDB(DB_PRIMARY);
            $dbr -> update('edusharing_resource',
            array('EDUSHARING_RESOURCE_PAGE_ID' => $article -> getId()),
            array('EDUSHARING_RESOURCE_ID = "' . $_resourceId . '"'),
            'Database::update'
            );

            /*
             * Set edu-sharing properties 
             */                            
            $edu_sharing = new stdClass;

            $edu_sharing -> id = $_id;
            $edu_sharing -> repid = parse_url($_id, PHP_URL_HOST);
            $edu_sharing -> height = $_height;
            $edu_sharing -> width = $_width;
            $edu_sharing -> mimetype = $_mimetype;
            $edu_sharing -> pageid = $article -> getId();
            $edu_sharing -> ticket = RequestContext::getMain()->getRequest()->getSession()->get('repository_ticket');
            $edu_sharing -> user = strtolower($wgUser -> getName());
            $edu_sharing -> float = $_float;
            $edu_sharing -> version = $_version;
            $edu_sharing -> versionShow = $_versionShow;
           
            $edu_sharing -> resourceid = $_resourceId;
            $edu_sharing -> appid = $hc['appid'];
            
            /*
             * UPDATE usage 
             */
            $eduws -> addUsage($edu_sharing);
        }

    return true;
    

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
        $wikiPage = WikiPage::factory( $title );

        $session= RequestContext::getMain()->getRequest()->getSession();
        
        /*
         * Get db access
         */
        $dbr = wfGetDB( DB_REPLICA );
        
        /*
         * Select all article's resources
         */

        $res = $dbr->select('edusharing_resource', // $table
            array('EDUSHARING_RESOURCE_ID', 'EDUSHARING_RESOURCE_PAGE_ID', 'EDUSHARING_RESOURCE_TITLE', 'EDUSHARING_RESOURCE_OBJECT_URL', 'EDUSHARING_RESOURCE_OBJECT_VERSION', 'EDUSHARING_RESOURCE_WIDTH', 'EDUSHARING_RESOURCE_HEIGHT', 'EDUSHARING_RESOURCE_FLOAT'), // $vars (columns of the table)
            'EDUSHARING_RESOURCE_PAGE_ID = ' . $wikiPage -> getId(), // $conds
            'Database::select', // $fname = 'Database::select',
            array('ORDER BY' => 'EDUSHARING_RESOURCE_ID ASC') // $options = array()
        );

        $old_list = array();
        foreach ($res as $row) {
            $old_list[$row -> EDUSHARING_RESOURCE_ID] = $row;
        }

        $eduws = new EduSharingWS();
        $hc = $eduws -> getHomeConfig();

        /*
         * Get edu-sharing tags from $text 
         */
        $matches = self::get_edutags('edusharing', $text);

        /*
         * For each resource found in text 
         */
        foreach ($matches as $edutag) {            
            $Response = simplexml_load_string($edutag);
            /*
             * For new resources insert db record and set usage, mark as processed
             */
            if ($Response['action'] == 'new') {

                $edu_sharing = new stdClass;

                $_id = (string)$Response['id'];
                $_width = (string)$Response['width'];
                $_height = (string)$Response['height'];
                $_mimetype = (string)$Response['mimetype'];
                $_float = (string)$Response['float'];
                $_version = (string)$Response['version'];
                $_versionShow = (string)$Response['versionShow'];


                /*
                 * Set edu-sharing properties 
                 */
                $edu_sharing -> id = $_id;
                
                $edu_sharing -> repid = parse_url($_id, PHP_URL_HOST);
                $edu_sharing -> height = $_height;
                $edu_sharing -> width = $_width;
                $edu_sharing -> mimetype = $_mimetype;
                $edu_sharing -> pageid = $wikiPage -> getId();
                
                $edu_sharing -> ticket = $session->get('repository_ticket');
                $edu_sharing -> user = strtolower($user -> getName());
                $edu_sharing -> float = $_float;
                $edu_sharing -> version = $_version;
                $edu_sharing -> versionShow = $_versionShow;

                /*
                 * Insert record
                 */
                $dbw = wfGetDB( DB_PRIMARY );
                $_data = array('EDUSHARING_RESOURCE_PAGE_ID' => $wikiPage->getId(), 'EDUSHARING_RESOURCE_OBJECT_URL' => $_id, 'EDUSHARING_RESOURCE_TITLE' => $title, 'EDUSHARING_RESOURCE_WIDTH' => $_width, 'EDUSHARING_RESOURCE_HEIGHT' => $_height, 'EDUSHARING_RESOURCE_FLOAT' => $_float);
                $dbw -> insert('edusharing_resource', $_data, 'Database::insert');
                $insert_id = $dbw -> insertId();
                $edu_sharing -> resourceid = $insert_id;
                
                $Response -> addAttribute('resourceid', $insert_id);
                
                $Response['action'] = 'processed';
                $_tag = html_entity_decode(str_replace('<?xml version="1.0"?>', '', $Response -> asXML()));

                /*
                 * Write properties to text
                 */
                $text = str_replace($edutag, $_tag, $text);

                $edu_sharing -> appid = $hc['appid'];

                /*
                 * Add usage to repository resource
                 */
                $pageId = $wikiPage -> getId();
                if(!empty($pageId))
                    $eduws -> addUsage($edu_sharing);
                
            } else if ($Response['action'] == 'processed') {               
                                
                /*
                 * Try to get record for this resource with select conditions article id and resource id.
                 * If no record can be found this resource must be copied from another page. So add new record and add usage.
                 */
                $dbr = wfGetDB( DB_REPLICA );
                $res = $dbr -> select('edusharing_resource',
                    array('EDUSHARING_RESOURCE_ID', 'EDUSHARING_RESOURCE_PAGE_ID'),
                    array('EDUSHARING_RESOURCE_PAGE_ID = ' . $wikiPage -> getId(), 'EDUSHARING_RESOURCE_ID = ' . $Response['resourceid']));
                
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
                    $edu_sharing = new stdClass;
                    
                    $_id = (string)$Response['id'];
                    $_width = (string)$Response['width'];
                    $_height = (string)$Response['height'];
                    $_mimetype = (string)$Response['mimetype'];
                    $_float = (string)$Response['float'];
                    $_version = (string)$Response['version'];
                    $_versionShow = (string)$Response['versionShow'];
                    
                    /*
                     * Set edu-sharing properties 
                     */
                    $edu_sharing -> id = $_id;
                    $edu_sharing -> repid = parse_url($_id, PHP_URL_HOST);
                    $edu_sharing -> height = $_height;
                    $edu_sharing -> width = $_width;
                    $edu_sharing -> mimetype = $_mimetype;
                    $edu_sharing -> pageid = $wikiPage -> getId();
                    $edu_sharing -> ticket = $session->get('repository_ticket');
                    $edu_sharing -> user = strtolower($user -> getName());
                    $edu_sharing -> float = $_float;
                    $edu_sharing -> version = $_version;
                    $edu_sharing -> versionShow = $_versionShow;
                    
                    /*
                     * Insert record 
                     */
                    $dbw = wfGetDB(DB_PRIMARY);
                    $_data = array('EDUSHARING_RESOURCE_PAGE_ID' => $wikiPage -> getId(), 'EDUSHARING_RESOURCE_OBJECT_URL' => $_id, 'EDUSHARING_RESOURCE_TITLE' => $title, 'EDUSHARING_RESOURCE_WIDTH' => $_width, 'EDUSHARING_RESOURCE_HEIGHT' => $_height, 'EDUSHARING_RESOURCE_FLOAT' => $_float);
                    $dbw -> insert('edusharing_resource', $_data, 'Database::insert');
                    $insert_id = $dbw -> insertId();
                    
                    $edu_sharing -> resourceid = $insert_id;
                    $Response['resourceid'] = $insert_id;
                    $_tag = str_replace('<?xml version="1.0"?>', '', $Response -> asXML());
                    $text = str_replace($edutag, $_tag, $text);
                    $edu_sharing -> appid = $hc['appid'];
                    
                    /*
                     * Add usage 
                     */
                    $eduws -> addUsage($edu_sharing);
                }
            }
        }

        /*
         * Delete resources that have been removed from article
         */
        foreach ($old_list as $item) {
           
            //usage2
            $params = array(
            		'eduRef' => $item -> EDUSHARING_RESOURCE_OBJECT_URL,
            		'user' => strtolower($user -> getName()),
            		'lmsId' => $hc['appid'],
            		'courseId' => $wikiPage -> getId(),
            		'resourceId' => $item -> EDUSHARING_RESOURCE_ID
            );

            /*
             * Delete usage
             */
            try {
            	$eduws -> delUsage($params);
            } catch(SoapFaul $e) {
				print_r($e);
            }
            /*
             * Delete record in db
             */
            $dbr = wfGetDB( DB_PRIMARY );
            $dbr -> delete('edusharing_resource', array('EDUSHARING_RESOURCE_ID = ' . $item -> EDUSHARING_RESOURCE_ID), $fname = 'Database::delete');
        }
        return true;
    }

    /**
     * Adds edu-sharing item to editor toolbar
     * 
     * @param &$toolbar
     * @return true
     */
    public static function editPageShowEditFormInitial(&$toolbar) {
        global $wgOut;
        $wgOut -> addModules('ext.eduSharing.dialog');
        return true;
    }

    /**
     * Adds hook to parser that handles edu-sharing tags
     * 
     * @param $parser
     * @return true
     */
    public static function wfEdusharingExtensionInit(Parser $parser) {

        global $wgUser;

        /**
         * When the parser sees the <edusharing> tag, it executes the render function (see below)
         */
        $parser -> setHook("edusharing", array( __CLASS__, 'wfEduSharingRender' ));

        $user = $wgUser;

        $eduws = new EduSharingWS();

        self::$hc = $eduws -> getHomeConfig();
        $configs = $eduws -> getConfigs();

        $ticket = $eduws -> getTicket();
        self::$ticket = $ticket;
        
        //RequestContext::getMain()->getRequest()->getSession()->set("repository_ticket", $ticket);
        //RequestContext::getMain()->getRequest()->getSession()->set("repository_home", self::$hc);

        global $wgOut, $wgServer, $wgScriptPath, $eduIconMimeVideo, $eduIconMimeAudio;
        
        $wgOut -> addJsConfigVars(array('eduticket' => $ticket));
        ### added:
        $wgOut -> addJsConfigVars(array('eduusername' => $user -> getName() ));
        $wgOut -> addJsConfigVars(array('eduappid' => self::$hc['appid'] ));
        
        ###
        $reurl = urlencode($wgServer . $wgScriptPath . '/extensions/EduSharing/populate.php');
        $wgOut -> addJsConfigVars(array('edugui' => self::$hc["edu_url"] . 'components/search?ticket=' . $ticket . '&reurl=' . $reurl.'&user='.strtolower($user -> getName())));
        $wgOut -> addJsConfigVars(array('edu_preview_icon_video' => $eduIconMimeVideo));
        $wgOut -> addJsConfigVars(array('edu_preview_icon_audio' => $eduIconMimeAudio));
        $wgOut -> addJsConfigVars(array('edupreview' => self::$hc["edu_url"] . 'preview?'));
        $wgOut -> addJsConfigVars(array('eduicon' => $wgServer . $wgScriptPath . '/extensions/EduSharing/resources/images/edu-icon.svg'));

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
       
        $loadedJs = false;

        if (!$loadedJs) {
            $parser -> getOutput() -> addModules('ext.eduSharing');
            $loadedJs = true;
        }
                
        /*
         * Set edu-sharing properties, params for proxy request
         * Render wrapper
         * 
         * $args['action'] === 'processed' - page view
         * $_GET['action'] == 'submit' - preview
         */
        if (isset($args['action']) && ($args['action'] === 'processed') || $_GET['action'] == 'submit') {

            global $wgUser, $wgServer, $wgScriptPath;
            $edu_sharing = new stdClass();

            $edu_sharing -> id = $args['id'];
            $eduObject = parse_url($edu_sharing -> id);
            $edu_sharing -> id = str_replace('/', '', $eduObject['path']);
            $edu_sharing -> appid = self::$hc['appid'];
            $edu_sharing -> repid = $eduObject['host'];           
            $edu_sharing -> resourceid = $args['resourceid'];
            $edu_sharing -> height = $args['height'];
            $edu_sharing -> width = $args['width'];
            $edu_sharing -> mimetype = $args['mimetype'];
            $edu_sharing -> page = $parser->mTitle->mArticleID;

            if(!empty($args['float'])){
            	 $edu_sharing -> float = $args['float'];
            } else {
            	 $edu_sharing -> float = 'none';
            }

            $param = '&oid=' . $edu_sharing -> id;
            $param .= '&resid=' . $edu_sharing -> resourceid;
            $param .= '&height=' . $edu_sharing -> height;
            $param .= '&width=' . $edu_sharing -> width;
            $param .= '&mime=' . $edu_sharing -> mimetype;
            $param .= '&pid=' . $edu_sharing -> page;
            $param .= '&appid=' . $edu_sharing -> appid;
            $param .= '&repid=' . $edu_sharing -> repid;
            $param .= '&printTitle=' . addslashes($input);
            $param .= '&language=' . $wgUser -> mOptions['language'];

            $dataUrl = SpecialPage::getTitleFor('EduRenderProxy')->getLocalUrl() . $param;

            switch($edu_sharing -> float) {
                case 'left': $style = "float: left; display: block; margin: 10px 10px 10px 0;"; break;
                case 'none': $style = "float: none; display: block; margin: 10px 0;"; break;
                case 'right': $style = "float: right; display: block; margin: 10px 0 10px 10px;"; break;
                case 'inline':
                default: $style = 'float: none; display: inline-block; margin: 0';
            }

            if(isset($args['action']) && ($args['action'] === 'processed')) {
                $wrapperStyle = 'style="height: ' . $edu_sharing -> height . 'px; width:' . $edu_sharing -> width . 'px; ' . $style . '"';                   
                $text = '<div '.$wrapperStyle.' class="edu_wrapper" id="content_wrapper' . $edu_sharing -> id . '-' . $edu_sharing -> resourceid . '"><div data-type="esObject" data-url="'.$dataUrl.'" class="spinnerContainer"><div class="inner"><div class="spinner1"></div></div><div class="inner"><div class="spinner2"></div></div><div class="inner"><div class="spinner3"></div></div></div></div>';
                
            } else {    
                $text = self::getPreview($edu_sharing, $input, $style);
            }
            
            return $text;

        } else {

            return 'Unknown edusharing action: "' . $args['action'] . '"';

        }

    }

    /**
     * Get the wrapped preview of a resource
     * images - image preview
     * audio - standard icon
     * video - standard ion
     * links - given title
     * 
     * @param $edu_sharing
     * @param $input
     * @param $style
     * @return string
     */
    public static function getPreview($edu_sharing, $input, $style) {
        global $eduIconMimeVideo, $eduIconMimeAudio;

        $session = RequestContext::getMain()->getRequest()->getSession();
        $wrapperStyle = "style=\"height:auto; width:auto; " . $style . "\"";
                    
            $mimeSwitchHelper = '';
            if(strpos($edu_sharing -> mimetype, 'image') !== false)
               $mimeSwitchHelper = 'image';
            else if(strpos($edu_sharing -> mimetype, 'audio') !== false)
               $mimeSwitchHelper = 'audio';
            else if(strpos($edu_sharing -> mimetype, 'video') !== false)
                $mimeSwitchHelper = 'video';
            else
                $mimeSwitchHelper = 'textlike';
            switch($mimeSwitchHelper) {
                case 'image':
                    $content = "<img src=\"".$session->get("repository_home")["edu_url"]."preview?nodeId=".$edu_sharing -> id."&ticket=".$session->get("repository_ticket")."\" width=\"".$edu_sharing -> width."\" height=\"".$edu_sharing -> height."\" />";
                    $content .= "<p>".$input."</p>";
                break;
                case 'audio':
                    $content = "<img src=\"".$eduIconMimeAudio."\" width=\"".$edu_sharing -> width."\" height=\"".$edu_sharing -> height."\"/>";
                    $content .= "<p>".$input."</p>";
                break;
                case 'video':
                    $content = "<img src=\"".$eduIconMimeVideo."\" width=\"".$edu_sharing -> width."\" height=\"".$edu_sharing -> height."\"/>";
                    $content .= "<p>".$input."</p>";
                break;
                case 'textlike':
                default: 
                    $content = "<a href=\"#\">".$input."</a>";
            }
                     
            $text = "<div class=\"edu_wrapper\" id=\"content_wrapper" . $edu_sharing -> id . "-" . $edu_sharing -> resourceid . "\" ".$wrapperStyle.">";
            $text .= $content;
            $text .= "</div>";
            return $text;
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
    public static function fnEdusharingDatabase(DatabaseUpdater $updater) {
        $updater -> addExtensionTable('edusharing_resource', dirname(__FILE__) . '/edu-resource.sql', true);
        return true;
    }


  // public static function onBeforePageDisplay(OutputPage &$out, Skin &$skin) {
              
  //     $dom = new DOMdocument();
  //     @$dom->loadHTML($out->getHTML()); #@ keeps loadHTMl silent
  //     $dom = $dom->getElementById('wpTextbox1');
  //     if($dom !== null){
  //         $out->addModuleStyles('ext.eduSharing.dialog');
  //         $out->addModules('ext.eduSharing.dialog');
  //     }   
  //     return true;
  // }
}
?>
