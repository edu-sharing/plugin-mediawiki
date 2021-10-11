EduSharing mediawiki extension
============================

This extension adds a selection dialogue to the VisualEditor and WikiEditor extensions to select resources from an edu-sharing repository for use on your wiki.

You should have installed either the [VisualEditor](https://www.mediawiki.org/wiki/Extension:VisualEditor) or the [WikiEditor](https://www.mediawiki.org/wiki/Extension:WikiEditor) (or both) to make use of this functionality.

This extension works with Mediawiki 1.35 or higher versions.
More information about edu-sharing can be found on the [edu-sharing homepage](http://www.edu-sharing.com).

Installation
------------
- Clone the git repositry into your Mediawiki extension folder ([MEDIAWIKI_INSTALL_DIR]/extensions/EduSharing/)
- use --recurse-submodules with git clone or execute git submodule update --init after cloning to get the edu-sharing api client
- Add/adjust the following values in LocalSettings.php
  - wfLoadExtension( 'EduSharing' );
- run php maintenance/update.php to create or update the necessary database table "edusharing_resource"
OR   
- Open [MEDIAWIKI_INSTALL_URL]/mw-config and upgrade your mediawiki to create/update the table.

Extension registration
----------------------
You will have to register the extension with an edu-sahing repository in order to use edu-sharing resources.

Adjust the following configuration values in your LocalSettings.php according to your repository:

- $wgEduSharingAppId = "your-wiki-app-id";
- $wgEduSharingAppDomain = "yourwiki.domain.tld";
- $wgEduSharingAppHost = "12.345.67.89"; 
- $wgEduSharingBaseUrl = 'https://redaktion-staging.openeduhub.net/edu-sharing';

go to the extension's maintenance-folder [MEDIAWIKI_INSTALL_DIR]/extensions/EduSharing/maintenance and run
php createKeys.php to generate a key pair for use with the repository and retrieve the repository's public key.
(Usage: php createKeys.php [--regenerate-key-pair] [--get-repo-key-only])

At this moment this extension has no automatic registration procedure so you have to register it manually.

Go to your repository's admin page and enter the follwing URL to provide the xml with the necessary configuration data:

https://yourwiki.domain.tld/index.php?title=Special:EduSharingRegister



Extension configuration
-----------------------
You can configure the extension to use anonymous access to the edu-sharing repository. By default this is used if the wiki user is not logged in but this behaviour can be enforced by adding

$wgEduSharingForceGuestUser = true;

to your LocalSettings.php file.

The default username to be used with the repository for anonymous access is "esguest". It can be configured setting

$wgEduSharingGuestUserName = 'YourGuestUserName';

to the appropriate value.



Contributing
------------
If you plan to contribute on a regular basis, please visit our [community site](http://edu-sharing-network.org/?lang=en).
