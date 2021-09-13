/*!
 * VisualEditor UserInterface MWEduSharingDialog class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */
/**
 * Dialog for editing MW edusharing.
 *
 * @class
 * @extends ve.ui.MWExtensionDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
 ve.ui.MWEduSharingDialog = function VeUiMWEduSharingDialog() {
	// Parent constructor
	ve.ui.MWEduSharingDialog.super.apply( this, arguments );

	// this.updateEduSharingContentsDebounced = OO.ui.debounce( this.updateEduSharingContents.bind( this ), 300 );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWEduSharingDialog, ve.ui.MWExtensionDialog );

/* Static Properties */

ve.ui.MWEduSharingDialog.static.name = 'mwEduSharing';

ve.ui.MWEduSharingDialog.static.title = OO.ui.deferMsg( 'visualeditor-mwedusharingdialog-title' );

ve.ui.MWEduSharingDialog.static.size = 'larger';

ve.ui.MWEduSharingDialog.static.allowedEmpty = true;

// ve.ui.MWEduSharingDialog.static.selfCloseEmptyBody = false; // default = false

ve.ui.MWEduSharingDialog.static.modelClasses = [ ve.dm.MWEduSharingNode ];

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWEduSharingDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.MWEduSharingDialog.super.prototype.initialize.call( this );

	// this.helpLink = new OO.ui.ButtonWidget( {
	// 	icon: 'help',
	// 	classes: [ 've-ui-mwEduSharingDialog-help' ],
	// 	title: ve.msg( 'visualeditor-mwedusharingdialog-help-title' ),
	// 	href: '',
	// 	target: '_blank'
	// } );

	this.$edusharing = $( '<div>' ).appendTo( this.$edusharingContainer );
	this.edusharing = null;

	// Panels
	this.indexLayout = new OO.ui.IndexLayout( {
		expanded: false,
		classes: [ 've-ui-mwEduSharingDialog-indexLayout' ]
	} );
	this.selectPanel = new OO.ui.TabPanelLayout( 'select', {
		expanded: false,
		padded: true,
		label: ve.msg( 'visualeditor-mwedusharingdialog-select' )
	} );
	this.optionsPanel = new OO.ui.TabPanelLayout( 'options', {
		expanded: false,
		label: ve.msg( 'visualeditor-mwedusharingdialog-options' )
	} );
	
	// Fields	
	this.id = new OO.ui.TextInputWidget( {
	} );
	this.idField = new OO.ui.FieldLayout( this.id, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-id' )
	} ).isVisible( false );

	this.caption = new OO.ui.TextInputWidget( {
	} );
	this.captionField = new OO.ui.FieldLayout( this.caption, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-caption' )
	// } ).isVisible( false );
	} );

	this.mimetype = new OO.ui.TextInputWidget( {
	} );
	this.mimetypeField = new OO.ui.FieldLayout( this.mimetype, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-mimetype' )
	} ).isVisible( false );

	this.version = new OO.ui.TextInputWidget( {
	} );
	this.versionField = new OO.ui.FieldLayout( this.version, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-version' )
	} ).isVisible( false );

	this.repotype = new OO.ui.TextInputWidget( {
	} );
	this.repotypeField = new OO.ui.FieldLayout( this.repotype, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-repotype' )
	} ).isVisible( false );

	this.versionshow = new OO.ui.RadioSelectInputWidget( {
    options: [
			{ data: 'latest', label: mw.msg('visualeditor-mwedusharingdialog-versionshow-latest') },
			{ data: 'current', label: mw.msg('visualeditor-mwedusharingdialog-versionshow-current') }
    ]
	} );
	this.versionshowField = new OO.ui.FieldLayout( this.versionshow, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-versionshow' )
	} );

	this.dimensions = new ve.ui.DimensionsWidget();
	this.dimensionsField = new OO.ui.FieldLayout( this.dimensions, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-size' )
	} );

	this.align = new ve.ui.AlignWidget( {
		dir: this.getDir()
	} );
	this.alignField = new OO.ui.FieldLayout( this.align, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-align' )
	} );

	this.optionsPanel.$element.append (
		this.idField.$element,
		this.captionField.$element,
		this.mimetypeField.$element,
		this.versionField.$element,
		this.repotypeField.$element,
		this.versionshowField.$element,
		this.dimensionsField.$element,
		this.alignField.$element
	);

	// Initialize
	this.indexLayout.addTabPanels( [
		this.selectPanel,
		// this.contentPanel,
		this.optionsPanel
	] );

	this.$body.append(
		this.$edusharingContainer,
		this.indexLayout.$element
		//this.helpLink.$element
	);

	var iframeSetup = {
		// html: '<iframe width="100%" height="900" id="edusharing" src="' + mw.config.get('edugui') + '"></iframe>'
		html: '<iframe id="edusharing" style="width: 100%; "></iframe>'
	}

	var eduIframe = $(iframeSetup.html);

	this.selectPanel.$element.append(
		eduIframe
	)

	var eduIframeShow = false;

	window.showEduFrame = function () {
			// Calculate iframe height
			// console.log($('.oo-ui-menuLayout-menu').height()); console.log($('.oo-ui-menuLayout-content').height());
			$('#edusharing').css('height', 'calc( 100% - ' + $('.oo-ui-menuLayout-menu').height() +  'px - ' + $('.oo-ui-menuLayout-content').height() +  'px )');
			if ( eduIframeShow === false ) { // Load only if not loaded before
				$('#edusharing').attr('src', mw.config.get('edugui'));
			eduIframeShow = true;
		}
	}

	window.hideEduFrame = function () {
		eduIframeShow = false;
	}
};

/**
 * Handle change events on the dimensions widget
 */
ve.ui.MWEduSharingDialog.prototype.onDimensionsChange = function () {
	this.updateActions();
};

/**
 * @inheritdoc ve.ui.MWExtensionWindow
 */
ve.ui.MWEduSharingDialog.prototype.insertOrUpdateNode = function () {
	// Parent method
	ve.ui.MWEduSharingDialog.super.prototype.insertOrUpdateNode.apply( this, arguments );

	// Update scalable
	this.scalable.setCurrentDimensions(
			this.dimensions.getDimensions()
	);
};

/**
 * @inheritdoc ve.ui.MWExtensionWindow
 */
ve.ui.MWEduSharingDialog.prototype.updateMwData = function ( mwData ) {

	this.indexLayout.setTabPanel( 'options' );

	var id, caption, mimetype, version, dimensions, versionshow, repotype;
	id = this.id.getValue();
	caption = this.caption.getValue();
	mimetype = this.mimetype.getValue();
	dimensions = this.dimensions.getDimensions();
	version = this.version.getValue();
	repotype = this.repotype.getValue();
	versionshow = this.versionshow.getValue();	


	// Parent method
	ve.ui.MWEduSharingDialog.super.prototype.updateMwData.call( this, mwData );

	// Set the edusharing tag attributes
	mwData.attrs.action = 'new'; // Regardless of whether inserting or updating an edu-sharing media we always need to set action tag attribute to 'new' to get a new resourceid (see below)
	mwData.body.extsrc = caption;
	mwData.attrs.id = id.toString();
	mwData.attrs.mimetype = mimetype.toString();
	mwData.attrs.version = version.toString();
	mwData.attrs.repotype = repotype.toString();
	mwData.attrs.versionshow = versionshow.toString();
	mwData.attrs.width = dimensions.width.toString();
	mwData.attrs.height = dimensions.height.toString();
	mwData.attrs.float = this.align.findSelectedItem().getData(); // The edusharing tag uses float, VE uses align, see also ve.ce.MWEduSharingNode.js
	// If updating an edu-sharing media we need to delete the resourceid tag attribute to get a new resourceid from edu-sharing, otherwise we get a permission error
	if ( mwData.attrs.hasOwnProperty('resourceid') ){
		delete mwData.attrs.resourceid;
	}
}

// /**
//  * @inheritdoc
//  */
// ve.ui.MWEduSharingDialog.prototype.getReadyProcess = function ( data ) {
// 	return ve.ui.MWEduSharingDialog.super.prototype.getReadyProcess.call( this, data )
// 		.next( function () {
// 			this.pushPending();
// 			this.setupEduSharing()
// 				.then( this.popPending.bind( this ) );
// 		}, this );
// };

/**
 * @inheritdoc
 */
ve.ui.MWEduSharingDialog.prototype.getSetupProcess = function ( data ) {

	this.indexLayout.getTabPanel( 'select' ).getTabItem().setElementId('edusharing-select');
	this.indexLayout.getTabPanel( 'options' ).getTabItem().setElementId('edusharing-options');
	showEduFrame();
	this.indexLayout.setTabPanel( 'select' );

	data = data || {};
	return ve.ui.MWEduSharingDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var mwAttrs = this.selectedNode && this.selectedNode.getAttribute( 'mw' ).attrs || {},
			mwBody = this.selectedNode && this.selectedNode.getAttribute( 'mw' ).body || {},
			isReadOnly = this.isReadOnly();
			
			var that = this;

			////////////////////////// from Moodle plugin

			window.addEventListener('message', function (event) {
				console.log('event.data.event: '); console.log(event.data.event);
				if (event.data.event == 'APPLY_NODE') {
					alert(event.data.event);
					var node = event.data.data;
					console.log('Received data: '); console.log(node);
				}
			}, false);

			////////////////////////

			window.setData = function(id, caption, mimetype, width, height, version, repotype) {
				console.log("window.setData from iframe: ", id, caption, mimetype, width, height, version, repotype);
				
				that.indexLayout.setTabPanel( 'options' );
				hideEduFrame();
				showEduFrame();
				$('#edusharing-options').show(); // New object is selected - so let's show the options tab

				that.id.setValue(id);
				that.caption.setValue(caption);
				that.mimetype.setValue(mimetype);
				that.version.setValue(version);
				that.repotype.setValue(repotype);

			};
		

			if ( this.selectedNode ) {
				this.scalable = this.selectedNode.getScalable();
			} else {
				this.scalable = ve.dm.MWEduSharingNode.static.createScalable(
					{ width: 400, height: 300 }
				);
			}

			// Events

			this.id.setValue( mwAttrs.id ).setDisabled( isReadOnly );
			
			this.caption.setValue( mwBody.extsrc ).setDisabled( isReadOnly );

			this.mimetype.setValue( mwAttrs.mimetype ).setDisabled( isReadOnly );

			this.version.setValue( mwAttrs.version ).setDisabled( isReadOnly );

			this.repotype.setValue( mwAttrs.repotype ).setDisabled( isReadOnly );

			this.versionshow.setValue( mwAttrs.versionshow ).setDisabled( isReadOnly );

			this.dimensions.setDimensions( this.scalable.getCurrentDimensions() ).setReadOnly( isReadOnly );

			// this.align.selectItemByData( mwAttrs.align || 'right' ).setDisabled( isReadOnly );
			this.align.selectItemByData( mwAttrs.float || 'right' ).setDisabled( isReadOnly ); // The edusharing tag uses float not align

			this.dimensions.connect( this, {
				widthChange: 'onDimensionsChange',
				heightChange: 'onDimensionsChange'
			} );

			this.caption.connect( this, { change: 'updateActions' } );

			this.versionshow.connect( this, { change: 'updateActions' } );

			this.align.connect( this, { choose: 'updateActions' } );
			
			if( this.id.value != ''){
				this.indexLayout.setTabPanel( 'options' ); // If the edusharing object already exists, we start with the options panel
			} else {
				// Hack: Hide the options tab to prevent insertion of an empty edusharing tag if no object is selected.
				// There seems to be no method to disable a tab - see: https://doc.wikimedia.org/VisualEditor/master/js/#!/api/OO.ui.TabPanelLayout-method-setTabItem
				$('#edusharing-options').hide();
			}
		}, this );
};

/**
 * @inheritdoc
 */
ve.ui.MWEduSharingDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWEduSharingDialog.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			// Events
			
			this.indexLayout.disconnect( this );

			this.id.disconnect( this );

			this.caption.disconnect( this );

			this.mimetype.disconnect( this );

			this.repotype.disconnect( this );

			this.version.disconnect( this );

			this.versionshow.disconnect( this );

			this.dimensions.disconnect( this );

			this.align.disconnect( this );

			if ( this.edusharing ) {
				this.edusharing.remove();
				this.edusharing = null;
			}
		}, this );
};

// A larger dialog
/**
 * @inheritdoc
 */

ve.ui.MWEduSharingDialog.prototype.getSizeProperties = function () {
 		return {
 			width: '905',
 			height: '1000'
 	};
};

// ve.ui.MWEduSharingDialog.prototype.getBodyHeight = function () {
//  	return 1000;
// };

/* Registration */

ve.ui.windowFactory.register( ve.ui.MWEduSharingDialog );