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
};

/* Inheritance */

OO.inheritClass( ve.ui.MWEduSharingDialog, ve.ui.MWExtensionDialog );

/* Static Properties */

ve.ui.MWEduSharingDialog.static.name = 'mwEduSharing';

ve.ui.MWEduSharingDialog.static.title = OO.ui.deferMsg( 'visualeditor-mwedusharingdialog-title' );

ve.ui.MWEduSharingDialog.static.size = 'large';

ve.ui.MWEduSharingDialog.static.allowedEmpty = true;

// ve.ui.MWEduSharingDialog.static.selfCloseEmptyBody = false; // default = false

ve.ui.MWEduSharingDialog.static.modelClasses = [ ve.dm.MWEduSharingNode ];

var previewUrl = mw.config.get( 'edupreview' );

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

	// Preview
	this.$previewContainer = $( '<div>' ).addClass( 've-ui-mwEduSharingDialog-preview' );
	this.$edusharing = $( '<div>' ).appendTo( this.$previewContainer );
	this.edusharing = null;

	// Panel
	this.indexLayout = new OO.ui.IndexLayout( {
		expanded: false,
		classes: [ 've-ui-mwEduSharingDialog-indexLayout' ]
	} );
	this.panel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true
	} );
	
	// Buttons & Fields	

	this.repoButton = new OO.ui.ButtonWidget( {
		id: 'repo-button',
		label: ve.msg( 'visualeditor-mwedusharingdialog-select' ),
		flags: [
			'primary',
			'progressive'
		]
	} );

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
	} );

	this.mediatype = new OO.ui.TextInputWidget( {
	} );
	this.mediatypeField = new OO.ui.FieldLayout( this.mediatype, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-mediatype' )
	} ).isVisible( false );

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
		id: 'field-dimensions',
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

	this.panel.$element.append (
		this.$previewContainer,
		this.repoButton.$element,
		this.idField.$element,
		this.captionField.$element,
		this.mediatypeField.$element,
		this.mimetypeField.$element,
		this.versionField.$element,
		this.repotypeField.$element,
		this.versionshowField.$element,
		this.dimensionsField.$element,
		this.alignField.$element
	);

	// Initialize
	this.indexLayout.$element.append(
		this.panel.$element
	);

	this.$body.append(
		this.indexLayout.$element
		//this.helpLink.$element
	);

	this.repoButton.$element.click ( function(){
		openRepo();
	} );
};

// Open new window and load the edu-sharing repo
function openRepo(){
	window.win = window.open( mw.config.get('edugui') );
}

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

	var id, caption, mediatype, mimetype, version, dimensions, versionshow, repotype;
	id = this.id.getValue();
	caption = this.caption.getValue();
	mediatype = this.mediatype.getValue();
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
	mwData.attrs.mediatype = mediatype.toString();
	mwData.attrs.mimetype = mimetype.toString();
	mwData.attrs.version = version.toString();
	mwData.attrs.repotype = repotype.toString();
	mwData.attrs.versionshow = versionshow.toString();
	mwData.attrs.width = dimensions.width.toString();
	if ( isNaN(dimensions.height) || dimensions.height === '' || dimensions.height === '0') // if 'auto', '' or '0'
		mwData.attrs.height = 'auto';	
	else
		mwData.attrs.height = dimensions.height.toString();
	console.log('dimensions.height: ');	console.log(dimensions.height);
	mwData.attrs.float = this.align.findSelectedItem().getData(); // The edusharing tag uses float, VE uses align, see also ve.ce.MWEduSharingNode.js
	// If updating an edu-sharing media we need to delete the resourceid tag attribute to get a new resourceid from edu-sharing, otherwise we get a permission error
	if ( mwData.attrs.hasOwnProperty('resourceid') ){
		delete mwData.attrs.resourceid;
	}
}

ve.ui.MWEduSharingDialog.prototype.getTypeSwitchHelper = function ( data ) {
	var elementtype, repotype, typeSwitchHelper;
	if ( data.mediatype !== undefined && data.mediatype !== '' )
		elementtype = data.mediatype;
	else if ( data.mimetype !== undefined && data.mediatype !== '' ) // For backward compatibility
		elementtype = data.mimetype;
	else
		elementtype = '';

	if ( data.repotype !== undefined && data.repotype !== '' ) // Existing object
		repotype = data.repotype;
	if ( data.repositoryType !== undefined && data.repositoryType !== '' ) // Object received from iframe
		repotype = data.repositoryType;

	if (elementtype.indexOf('image') !== -1)
		typeSwitchHelper = 'image';
	else if (elementtype.indexOf('audio') !== -1)
		typeSwitchHelper = 'audio';
	else if (elementtype.indexOf('video') !== -1 || repotype.indexOf('YOUTUBE') !== -1)
		typeSwitchHelper = 'video';
	else
		typeSwitchHelper = 'textlike';

	return typeSwitchHelper;
}

/**
 * @inheritdoc
 */
ve.ui.MWEduSharingDialog.prototype.getSetupProcess = function ( data ) {

	data = data || {};
	return ve.ui.MWEduSharingDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			var mwAttrs = this.selectedNode && this.selectedNode.getAttribute( 'mw' ).attrs || {},
			mwBody = this.selectedNode && this.selectedNode.getAttribute( 'mw' ).body || {},
			isReadOnly = this.isReadOnly();

			var that = this, node;
			// Receive data from iframe
			window.addEventListener('message', handleRepo, false);
			function handleRepo( event ){
				if(event.data.event == 'APPLY_NODE'){
					node = event.data.data;
					// console.log('event.data.data: '); console.log(event.data.data);

					window.win.close();

					that.id.setValue(node.objectUrl);
					that.caption.setValue(node.title);
					that.mediatype.setValue(node.mediatype);
					that.mimetype.setValue(node.mimetype);
					that.version.setValue(node.content.version);
					that.repotype.setValue(node.repositoryType);

					var typeSwitchHelper = ve.ui.MWEduSharingDialog.prototype.getTypeSwitchHelper( node );
					if ( typeSwitchHelper === 'textlike' ){
						$('#field-dimensions').hide();
						that.$edusharing.html( '' );
					} else {
						$('#field-dimensions').show();
						var mwId = node.objectUrl.substr(14),
						url = previewUrl + 'nodeId=' + mwId;
						that.$edusharing.html( '<img src="' + url + '" alt="" style="width: 100%; height: auto; "/>' );
					}
					
					window.removeEventListener('message', handleRepo, false );
				}
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

			this.mediatype.setValue( mwAttrs.mediatype ).setDisabled( isReadOnly );

			this.mimetype.setValue( mwAttrs.mimetype ).setDisabled( isReadOnly );

			this.version.setValue( mwAttrs.version ).setDisabled( isReadOnly );

			this.repotype.setValue( mwAttrs.repotype ).setDisabled( isReadOnly );

			this.versionshow.setValue( mwAttrs.versionshow ).setDisabled( isReadOnly );

			this.dimensions.setDimensions( this.scalable.getCurrentDimensions() ).setReadOnly( isReadOnly );

			if ( this.selectedNode ) {
				// alert(this.indexLayout.getElementId('repo-button'));
				this.repoButton.setLabel( ve.msg('visualeditor-mwedusharingdialog-change') );
				var typeSwitchHelper = this.getTypeSwitchHelper( mwAttrs );
				if ( typeSwitchHelper === 'textlike' ){
					$('#field-dimensions').hide();
					this.$edusharing.html( '' );
				} else {
					$('#field-dimensions').show();
					var mwId = mwAttrs.id.substr(14),
					url = previewUrl + 'nodeId=' + mwId;
					this.$edusharing.html( '<img src="' + url + '" alt="" style="width: 100%; height: auto; "/>' );
				}
			} else {
				this.repoButton.setLabel( ve.msg('visualeditor-mwedusharingdialog-select') );
				this.$edusharing.html( '' );
			}
			
			// this.align.selectItemByData( mwAttrs.align || 'right' ).setDisabled( isReadOnly );
			this.align.selectItemByData( mwAttrs.float || 'right' ).setDisabled( isReadOnly ); // The edusharing tag uses float not align

			this.dimensions.connect( this, {
				widthChange: 'onDimensionsChange',
				heightChange: 'onDimensionsChange'
			} );

			this.caption.connect( this, { change: 'updateActions' } );

			this.versionshow.connect( this, { change: 'updateActions' } );

			this.align.connect( this, { choose: 'updateActions' } );
			
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

			this.mediatype.disconnect( this );

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

// /**
//  * @inheritdoc
//  */

// ve.ui.MWEduSharingDialog.prototype.getSizeProperties = function () {
//  		return {
//  			width: '905',
//  			height: '700'
//  	};
// };

/**
 * @inheritdoc
 */
 ve.ui.MWEduSharingDialog.prototype.getBodyHeight = function () {
	return 700;
};

/* Registration */
ve.ui.windowFactory.register( ve.ui.MWEduSharingDialog );