/*!
 * VisualEditor UserInterface MWEduSharingDialog class.
 *
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki edusharing dialog.
 *
 * @class
 * @extends ve.ui.MWExtensionDialog
 *
 * @constructor
 * @param {Object} [element]
 */
ve.ui.MWEduSharingDialog = function VeUiMWEduSharingDialog() {
	// Parent constructor
	ve.ui.MWEduSharingDialog.super.apply(this, arguments);

	// Properties
	this.edusharingModel = null;
	this.mode = '';
	this.cachedRawData = null;
	this.listeningToInputChanges = true;
};

/* Inheritance */

OO.inheritClass(ve.ui.MWEduSharingDialog, ve.ui.MWExtensionDialog);

/* Static properties */

ve.ui.MWEduSharingDialog.static.name = 'edusharing';

ve.ui.MWEduSharingDialog.static.title = OO.ui.deferMsg('edusharing-ve-dialog-edit-title');

ve.ui.MWEduSharingDialog.static.size = 'larger';

ve.ui.MWEduSharingDialog.static.modelClasses = [ve.dm.MWEduSharingNode];

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWEduSharingDialog.prototype.getBodyHeight = function () {
	// FIXME: This should depend on the dialog's content.
	return 500;
};

/**
 * @inheritdoc
 */
ve.ui.MWEduSharingDialog.prototype.initialize = function () {

	var eduObject,
		eduCaption,
		eduHeight,
		eduWidth,
		eduRepotype,
		eduMimetype,
		eduFloat,
		eduVersionShow,
		eduVersion;

	var selectPage, parameterPage;

	// Parent method
	ve.ui.MWEduSharingDialog.super.prototype.initialize.call(this);

	// /* Root layout */
	this.rootLayout = new OO.ui.IndexLayout({
		classes: ['ve-ui-mwEduSharingDialog-panel-root']
	});

	var iframeSetup = {
		html: '<iframe width="100%" height="800" id="eduframe" src="' + mw.config.get('edugui') + '"></iframe>',
	}

	var eduIframe = $(iframeSetup.html);

	// this.rootLayout = new OO.ui.TabPanelLayout({
	// 	classes: ['ve-ui-mwEduSharingDialog-panel-root'],
	// 	expanded: false,
	// 	framed: false,
	// 	padded: false,
	// 	//		$content: content,
	// });

	this.selectPage = new OO.ui.TabPanelLayout('select', {
		label: ve.msg('edusharing-ve-dialog-edit-page-select')
	});
	this.parameterPage = new OO.ui.TabPanelLayout('parameter', {
		label: ve.msg('edusharing-ve-dialog-edit-page-parameter')
	});

	this.rootLayout.addTabPanels([
		this.selectPage, this.parameterPage
	]);

	this.selectPage.$element.append(
		eduIframe
	)


	this.eduCaption = new OO.ui.TextInputWidget({ value: 'jskjdlks' });
	var eduCaptionField = new OO.ui.FieldLayout(this.eduCaption, {
		label: ve.msg('edusharing-ve-dialog-edit-field-caption'),
		align: 'top'
	});

	this.eduCaption.connect(this, {
		change: 'onDataInputChange'
	});

	this.eduVersionConfig = {
		options: [
			{ data: 'current', label: mw.msg('edusharing-ve-dialog-edit-version-current') },
			{ data: 'latest', label: mw.msg('edusharing-ve-dialog-edit-version-latest') }
		]
	};

	// Properties
	var eduVersion = new OO.ui.RadioSelectInputWidget(this.eduVersionConfig);



	// Position
	this.positionSelect = new ve.ui.AlignWidget({
		dir: this.getDir()
	});
	positionSelectField = new OO.ui.FieldLayout(this.positionSelect);
	this.positionCheckbox = new OO.ui.CheckboxInputWidget();
	positionCheckboxField = new OO.ui.FieldLayout(this.positionCheckbox, {
		$overlay: this.$overlay,
		align: 'inline',
		label: ve.msg('visualeditor-dialog-media-position-checkbox'),
		help: ve.msg('visualeditor-dialog-media-position-checkbox-help')
	});
	positionFieldset = new OO.ui.FieldsetLayout({
		$overlay: this.$overlay,
		label: ve.msg('visualeditor-dialog-media-position-section'),
		help: ve.msg('visualeditor-dialog-media-position-section-help')
	});
	positionFieldset.addItems([
		positionCheckboxField,
		positionSelectField
	]);

	this.parameterPage.$element.append(
		eduCaptionField.$element,
		eduVersion.$element,
		positionFieldset.$element
	)

	// Initialization
	this.$body.append(this.rootLayout.$element);
};

/**
 * @inheritdoc
 */
ve.ui.MWEduSharingDialog.prototype.getSetupProcess = function (data) {
	return ve.ui.MWEduSharingDialog.super.prototype.getSetupProcess.call(this, data)
		.next(function () {
			var spec, newElement;

			this.getFragment().getSurface().pushStaging();

			// Create new edusharing node if not present (insert mode)
			if (!this.selectedNode) {
				newElement = this.getNewElement();
				this.fragment = this.getFragment().insertContent([
					newElement,
					{ type: '/' + newElement.type }
				]);
				this.getFragment().select();
				this.selectedNode = this.getFragment().getSelectedNode();
			}

			// Set up model
			spec = ve.copy(this.selectedNode.getSpec());



			this.edusharingModel = new ve.dm.MWEduSharingModel(spec);
			this.edusharingModel.connect(this, {
				specChange: 'onSpecChange'
			});

			// Set up default values
			this.setupFormValues();

			// // If parsing fails here, cached raw data can simply remain null
			// try {
			// 	this.cachedRawData = JSON.parse(this.jsonTextInput.getValue());
			// } catch (err) { }

			this.checkChanges();
		}, this);
};

/**
 * @inheritdoc
 */
ve.ui.MWEduSharingDialog.prototype.getTeardownProcess = function (data) {
	return ve.ui.MWEduSharingDialog.super.prototype.getTeardownProcess.call(this, data)
		.first(function () {
			// Kill model
			this.edusharingModel.disconnect(this);

			this.edusharingModel = null;

			this.rootLayout.resetScroll();

			// Clear data page
			// this.dataTable.clearWithProperties();

			// Kill staging
			if (data === undefined) {
				this.getFragment().getSurface().popStaging();
				this.getFragment().update(this.getFragment().getSurface().getSelection());
			}
		}, this);
};

/**
 * @inheritdoc
 */
ve.ui.MWEduSharingDialog.prototype.getActionProcess = function (action) {
	switch (action) {
		case 'done':
			return new OO.ui.Process(function () {
				// 

				this.edusharingModel.applyChanges(this.selectedNode, this.getFragment().getSurface());
				this.close({ action: action });
			}, this);

		default:
			return ve.ui.MWEduSharingDialog.super.prototype.getActionProcess.call(this, action);
	}
};

/**
 * Setup initial values in the dialog
 *
 * @private
 */
ve.ui.MWEduSharingDialog.prototype.setupFormValues = function () {
	// var edusharingType = this.edusharingModel.getEduSharingType(),
	// 	edusharingSize = this.edusharingModel.getSize(),
	// var eduVersions = this.edusharingModel.getEduVersionObject();
	var spec = this.edusharingModel.getSpec();
	console.log(spec)
	this.eduCaption.setValue(spec.caption)

	// 	readOnly = this.isReadOnly(),
	// 	options = [
	// 		{
	// 			data: 'bar',
	// 			label: ve.msg('edusharing-ve-dialog-edit-type-bar')
	// 		},
	// 		{
	// 			data: 'area',
	// 			label: ve.msg('edusharing-ve-dialog-edit-type-area')
	// 		},
	// 		{
	// 			data: 'line',
	// 			label: ve.msg('edusharing-ve-dialog-edit-type-line')
	// 		}
	// 	],
	// 	unknownEduSharingTypeOption = {
	// 		data: 'unknown',
	// 		label: ve.msg('edusharing-ve-dialog-edit-type-unknown')
	// 	},
	// 	dataFields = this.edusharingModel.getPipelineFields(0),
	// 	eduVersion, i;

	// // EduSharing type
	// if (edusharingType === 'unknown') {
	// 	options.push(unknownEduSharingTypeOption);
	// }

	// this.edusharingTypeDropdownInput
	// 	.setOptions(options)
	// 	.setValue(edusharingType)
	// 	.setDisabled(readOnly);

	// // Size
	// this.sizeWidget.setScalable(new ve.dm.Scalable({
	// 	currentDimensions: {
	// 		width: edusharingSize.width,
	// 		height: edusharingSize.height
	// 	},
	// 	minDimensions: ve.dm.MWEduSharingModel.static.minDimensions,
	// 	fixedRatio: false
	// }));
	// this.sizeWidget.setDisabled(readOnly);

	// // EduVersion
	// this.eduVersionAutoCheckbox.setSelected(this.edusharingModel.isEduVersionAutomatic())
	// 	.setDisabled(readOnly);
	// for (eduVersion in eduVersions) {
	// 	if (Object.prototype.hasOwnProperty.call(eduVersions, eduVersion)) {
	// 		this.eduVersionInputs[eduVersion].setValue(eduVersions[eduVersion])
	// 			.setReadOnly(readOnly);
	// 	}
	// }

	// // Data
	// for (i = 0; i < dataFields.length; i++) {
	// 	this.dataTable.insertColumn(null, null, dataFields[i], dataFields[i]);
	// }

	// this.dataTable.setDisabled(readOnly);

	// this.updateDataPage();

	// // JSON text input
	// this.jsonTextInput
	// 	.setValue(this.edusharingModel.getSpecString())
	// 	.setReadOnly(readOnly)
	// 	.clearUndoStack();
};

/**
 * Update data page widgets based on the current spec
 */
ve.ui.MWEduSharingDialog.prototype.updateDataPage = function () {
	var pipeline = this.edusharingModel.getPipeline(0),
		i, row, field;

	for (i = 0; i < pipeline.values.length; i++) {
		row = [];

		for (field in pipeline.values[i]) {
			if (Object.prototype.hasOwnProperty.call(pipeline.values[i], field)) {
				row.push(pipeline.values[i][field]);
			}
		}

		// this.dataTable.insertRow(row);
	}
};

/**
 * Validate raw data input
 *
 * @private
 * @param {string} value The new input value
 * @return {boolean} Data is valid
 */
ve.ui.MWEduSharingDialog.prototype.validateRawData = function (value) {
	var isValid = !$.isEmptyObject(ve.dm.MWEduSharingNode.static.parseSpecString(value)),
		label = (isValid) ? '' : ve.msg('edusharing-ve-dialog-edit-json-invalid');

	this.setLabel(label);

	return isValid;
};

// /**
//  * Handle spec string input change
//  *
//  * @private
//  * @param {string} value The text input value
//  */
// ve.ui.MWEduSharingDialog.prototype.onSpecStringInputChange = function (value) {
// 	var newRawData;

// 	try {
// 		// If parsing fails here, nothing more needs to happen
// 		newRawData = JSON.parse(value);

// 		// Only pass changes to model if there was anything worthwhile to change
// 		if (!OO.compare(this.cachedRawData, newRawData)) {
// 			this.cachedRawData = newRawData;
// 			this.edusharingModel.setSpecFromString(value);
// 		}
// 	} catch (err) { }
// };

/**
 * Handle edusharing type changes
 *
 * @param {string} value The new edusharing type
 */
ve.ui.MWEduSharingDialog.prototype.onEduSharingTypeInputChange = function (value) {
	this.unknownEduSharingTypeWarningLabel.toggle(value === 'unknown');

	if (value !== 'unknown') {
		this.edusharingModel.switchEduSharingType(value);
	}
};

/**
 * Handle data input changes
 *
 * @private
 * @param {number} rowIndex The index of the row that changed
 * @param {string} rowKey The key of the row that changed, or `undefined` if it doesn't exist
 * @param {number} colIndex The index of the column that changed
 * @param {string} colKey The key of the column that changed, or `undefined` if it doesn't exist
 * @param {string} value The new value
 */
ve.ui.MWEduSharingDialog.prototype.onDataInputChange = function (
	e
) {
	this.edusharingModel.setCaption(e);
};


/**
 * Handle page set events on the root layout
 *
 * @param {OO.ui.PageLayout} page Set page
 */
ve.ui.MWEduSharingDialog.prototype.onRootLayoutSet = function (page) {
	if (page.getName() === 'raw') {
		//	this.jsonTextInput.adjustSize(true);
	}
};

/**
 * Handle size widget changes
 *
 * @param {Object} dimensions New dimensions
 */
ve.ui.MWEduSharingDialog.prototype.onSizeWidgetChange = function (dimensions) {
	if (this.sizeWidget.isValid()) {
		this.edusharingModel.setWidth(dimensions.width);
		this.edusharingModel.setHeight(dimensions.height);
	}
	this.checkChanges();
};

/**
 * Handle eduVersion changes
 *
 * @param {string} key 'top', 'bottom', 'left' or 'right'
 * @param {string} value The new value
 */
ve.ui.MWEduSharingDialog.prototype.onEduVersionInputChange = function (key, value) {
	if (value !== '') {
		this.edusharingModel.setEduVersion(key, +value);
	}
};

/**
 * Handle model spec change events
 *
 * @private
 */
ve.ui.MWEduSharingDialog.prototype.onSpecChange = function () {
	var eduVersion,
		eduVersionAuto = this.edusharingModel.isEduVersionAutomatic(),
		eduVersionObj = this.edusharingModel.getEduVersionObject();

	if (this.listeningToInputChanges) {
		this.listeningToInputChanges = false;

		this.jsonTextInput.setValue(this.edusharingModel.getSpecString());

		if (eduVersionAuto) {
			// Clear eduVersion table if set to automatic
			for (eduVersion in this.eduVersionInputs) {
				this.eduVersionInputs[eduVersion].setValue('');
			}
		} else {
			// Fill eduVersion table with model values if set to manual
			for (eduVersion in eduVersionObj) {
				if (Object.prototype.hasOwnProperty.call(eduVersionObj, eduVersion)) {
					this.eduVersionInputs[eduVersion].setValue(eduVersionObj[eduVersion]);
				}
			}
		}

		for (eduVersion in this.eduVersionInputs) {
			this.eduVersionInputs[eduVersion].setDisabled(eduVersionAuto);
		}

		this.listeningToInputChanges = true;

		this.checkChanges();
	}
};

/**
 * Check for overall validity and enables/disables action abilities accordingly
 *
 * @private
 */
ve.ui.MWEduSharingDialog.prototype.checkChanges = function () {

	var dialog = this;
	dialog.actions.setAbilities({ done: true });

	// // Synchronous validation
	// if (!this.sizeWidget.isValid()) {
	// 	this.actions.setAbilities({ done: false });
	// 	return;
	// }

	// // Asynchronous validation
	// this.jsonTextInput.getValidity().then(
	// 	function () {
	// 		dialog.actions.setAbilities({
	// 			done: (dialog.mode === 'insert') || dialog.edusharingModel.hasBeenChanged()
	// 		});
	// 	},
	// 	function () {
	// 		dialog.actions.setAbilities({ done: false });
	// 	}
	// );
};

/* Registration */

ve.ui.windowFactory.register(ve.ui.MWEduSharingDialog);
