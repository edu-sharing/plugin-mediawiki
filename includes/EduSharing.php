<?php
/**
 * Main file of edu-sharing extension called in {MediWiki-Installation}/LocalSettings.php
 * Implements hooks (see http://www.mediawiki.org/wiki/Manual:Hooks) to enhance MediaWiki functionality
 */



/**
 * Protect against register_globals vulnerabilities.
 * This line must be present before any global variable is referenced.
 */
if (!defined('MEDIAWIKI')) {
    echo("This is an extension to the MediaWiki package and cannot be run standalone.\n");
    die(-1);

}

include (dirname(__FILE__) . '/edu-sharing.settings.php');
include (dirname(__FILE__) . '/classes/ESApp.php');
include (dirname(__FILE__) . '/classes/edu-sharingWS.php');


/**
 * Information about extension displayed at index.php/Spezial:Version
 */
global $wgExtensionCredits;
$wgExtensionCredits['validextensionclass'][] = array('path' => __FILE__, 'name' => 'edu-sharing', 'author' => 'Hupfer, Rotzoll, Hippeli', 'url' => 'https://www.mediawiki.org/wiki/Extension:NIL', 'description' => 'This extension should allow in the future the integration of content from a edu-sharing repo.', 'version' => 0.1, );

/**
 * Add i18n
 */
$wgExtensionMessagesFiles['edu-sharing'] = dirname(__FILE__) . '/edu-sharing.i18n.php';

?>
