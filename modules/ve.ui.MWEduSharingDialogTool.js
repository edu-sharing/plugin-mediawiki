/**
 * VisualEditor UserInterface MWEduSharingDialogTool class for MediaWiki 1.43.
 *
 * This class provides a toolbar tool for inserting and editing EduSharing content in the VisualEditor.
 * It supports opening a dialog for embedding EduSharing resources (e.g., images, videos).
 *
 * @author   Jan Böhme <jan@idea-sketch.com>
 * @author   Uwe Schützenmeister <uwe@idea-sketch.com>
 * @license  MIT
 * @since    MediaWiki 1.43
 */

/**
 * MediaWiki UserInterface tool for EduSharing dialog.
 *
 * @class ve.ui.MWEduSharingDialogTool
 * @extends ve.ui.FragmentWindowTool
 *
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup Tool group to which the tool belongs
 * @param {Object} [config] Configuration options
 */
ve.ui.MWEduSharingDialogTool = function VeUiMWEduSharingDialogTool() {
	ve.ui.MWEduSharingDialogTool.super.apply( this, arguments );
};

// Inheritance
OO.inheritClass( ve.ui.MWEduSharingDialogTool, ve.ui.FragmentWindowTool );

// Static Properties
ve.ui.MWEduSharingDialogTool.static.name = 'mwEduSharing';
ve.ui.MWEduSharingDialogTool.static.group = 'object';
ve.ui.MWEduSharingDialogTool.static.icon = 'edusharing';
ve.ui.MWEduSharingDialogTool.static.title = OO.ui.deferMsg( 'visualeditor-mwedusharingdialog-button' );
ve.ui.MWEduSharingDialogTool.static.modelClasses = [ ve.dm.MWEduSharingNode ];
ve.ui.MWEduSharingDialogTool.static.commandName = 'mwEduSharing';

// Registration
ve.ui.toolFactory.register( ve.ui.MWEduSharingDialogTool );

// Commands
ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'mwEduSharing', 'window', 'open',
		{
			args: [ 'mwEduSharing' ],
			supportedSelections: [ 'linear' ]
		}
	)
);
