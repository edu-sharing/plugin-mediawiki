/**
 * MediaWiki UserInterface edusharing tool.
 *
 * @class
 * @extends ve.ui.FragmentWindowTool
 * @constructor
 * @param {OO.ui.ToolGroup} toolGroup
 * @param {Object} [config] Configuration options
 */
ve.ui.MWEduSharingDialogTool = function VeUiMWEduSharingDialogTool() {
	ve.ui.MWEduSharingDialogTool.super.apply(this, arguments);
};

/* Inheritance */

OO.inheritClass(ve.ui.MWEduSharingDialogTool, ve.ui.FragmentWindowTool);

/* Static properties */

ve.ui.MWEduSharingDialogTool.static.name = 'edusharing';
ve.ui.MWEduSharingDialogTool.static.group = 'object';
ve.ui.MWEduSharingDialogTool.static.icon = 'edusharing';
ve.ui.MWEduSharingDialogTool.static.title =
	OO.ui.deferMsg('edusharing-ve-dialog-button');
ve.ui.MWEduSharingDialogTool.static.modelClasses = [ve.dm.MWEduSharingNode];
ve.ui.MWEduSharingDialogTool.static.commandName = 'edusharing';

/* Registration */

ve.ui.toolFactory.register(ve.ui.MWEduSharingDialogTool);

/* Commands */

ve.ui.commandRegistry.register(
	new ve.ui.Command(
		'edusharing', 'window', 'open',
		{ args: ['edusharing'], size: 'larger', supportedSelections: ['linear'] }
	)
);
