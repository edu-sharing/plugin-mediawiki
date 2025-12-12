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

class EduSharingInstallHooks implements \MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook {

	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dir = __DIR__ . '/../sql';

		if ( $updater->getDB()->getType() === 'mysql' ) {
			// Adds table 'edusharing_resource' to wiki db
			$updater->addExtensionTable(
				'edusharing_resource',
				"$dir/EduSharing.sql"
			);

			// Patches for existing installations:
			// Adds field + index for "usageid to existing "table 'edusharing_resource' when updating
			$updater->addExtensionField(
				'edusharing_resource',
				'EDUSHARING_RESOURCE_USAGE',
				"$dir/EduSharingAddUsageField.sql"
			);

			$updater->addExtensionIndex(
				'edusharing_resource',
				'id_usage',
				"$dir/EduSharingAddUsageIndex.sql"
			);

			// Modifies field 'EDUSHARING_RESOURCE_PAGE_ID' to allow NULL values
			$updater->modifyExtensionField(
				'edusharing_resource',
				'EDUSHARING_RESOURCE_PAGE_ID',
				"$dir/EduSharingAllowNullForPageId.sql"
			);
		}
	}
}
