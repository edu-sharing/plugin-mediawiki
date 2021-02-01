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
class EduSharingHooks {

  public static function onBeforePageDisplay(OutputPage &$out, Skin &$skin) {
              
      $dom = new DOMdocument();
      @$dom->loadHTML($out->getHTML()); #@ keeps loadHTMl silent
      $dom = $dom->getElementById('wpTextbox1');
      if($dom !== null){
          $out->addModuleStyles('ext.eduSharing.dialog');
          $out->addModules('ext.eduSharing.dialog');
      }   
      return true;
  }
}
?>
