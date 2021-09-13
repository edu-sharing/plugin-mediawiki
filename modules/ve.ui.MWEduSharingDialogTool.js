/*!
 * VisualEditor MediaWiki UserInterface gallery tool class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki UserInterface gallery tool.
 *
 * @class
 * @extends ve.ui.FragmentWindowTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */
ve.ui.MWEduSharingDialogTool = function VeUiMWEduSharingDialogTool() {
	ve.ui.MWEduSharingDialogTool.super.apply( this, arguments );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWEduSharingDialogTool, ve.ui.FragmentWindowTool );

/* Static properties */

ve.ui.MWEduSharingDialogTool.static.name = 'mwEduSharing';
ve.ui.MWEduSharingDialogTool.static.group = 'object';
ve.ui.MWEduSharingDialogTool.static.icon = 'edusharing';
ve.ui.MWEduSharingDialogTool.static.title = OO.ui.deferMsg( 'visualeditor-mwedusharingdialog-button' );
ve.ui.MWEduSharingDialogTool.static.modelClasses = [ ve.dm.MWEduSharingNode ];
ve.ui.MWEduSharingDialogTool.static.commandName = 'mwEduSharing';

/* Registration */

ve.ui.toolFactory.register( ve.ui.MWEduSharingDialogTool );

/* Commands */

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'mwEduSharing', 'window', 'open',
		{ args: [ 'mwEduSharing' ], supportedSelections: [ 'linear' ] }
	)
);