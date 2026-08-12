/**
 * VisualEditor ContentEditable MWEduSharingNode class
 *
 * This class provides a custom node for embedding edu-sharing content in the VisualEditor.
 * It supports interactive and static rendering of edu-sharing resources (e.g., images, videos).
 *
 * @author   Jan Böhme <jan@idea-sketch.com>
 * @author   Uwe Schützenmeister <uwe@idea-sketch.com>
 * @license  MIT
 */

/**
 * Dialog for editing MW EduSharing content.
 *
 * @class ve.ui.MWEduSharingDialog
 * @extends ve.ui.MWExtensionDialog
 *
 * @constructor
 * @param {Object} [config] Configuration options
 */
ve.ui.MWEduSharingDialog = function VeUiMWEduSharingDialog() {
	// Parent constructor
	ve.ui.MWEduSharingDialog.super.apply( this, arguments );
};

// Inheritance
OO.inheritClass( ve.ui.MWEduSharingDialog, ve.ui.MWExtensionDialog );

// Static Properties
ve.ui.MWEduSharingDialog.static.name = 'mwEduSharing';
ve.ui.MWEduSharingDialog.static.title = OO.ui.deferMsg( 'visualeditor-mwedusharingdialog-title' );
ve.ui.MWEduSharingDialog.static.size = 'large';
ve.ui.MWEduSharingDialog.static.allowedEmpty = true;
ve.ui.MWEduSharingDialog.static.modelClasses = [ ve.dm.MWEduSharingNode ];

const previewBaseUrl = mw.config.get( 'edupreview' );
let repositoryWindow = null;

/**
 * Returns a repository preview URL for old ccrep IDs and current UUIDs.
 *
 * @param {string} id Repository object ID
 * @param {string} [providedUrl] Preview URL supplied by the repository picker
 * @return {string} Preview URL
 */
function getPreviewUrl( id, providedUrl ) {
	if ( providedUrl ) {
		return providedUrl;
	}
	const nodeId = ( id || '' ).replace( /^ccrep:\/\/[^/]+\//, '' );
	return previewBaseUrl + 'nodeId=' + encodeURIComponent( nodeId );
}

/**
 * Renders a preview image without injecting repository data as HTML.
 *
 * @param {jQuery} $container Preview container
 * @param {string} url Preview URL
 * @param {string} alt Alternative text
 */
function renderPreview( $container, url, alt ) {
	const $image = $( '<img>' )
		.addClass( 'ext-edusharing-dialog-preview-image' )
		.attr( { src: url, alt: alt || 'Preview' } )
		.css( { maxWidth: '100%', height: 'auto' } );
	$container.empty().append(
		$( '<div>' ).addClass( 'ext-edusharing-dialog-preview-container' ).append( $image )
	);
}

/**
 * Initializes the dialog.
 *
 * @method
 */
ve.ui.MWEduSharingDialog.prototype.initialize = function () {
	// Parent method
	ve.ui.MWEduSharingDialog.super.prototype.initialize.call( this );

	this.$previewContainer = $( '<div>' ).addClass( 'ext-edusharing-dialog-preview' );
	this.$edusharing = $( '<div>' ).appendTo( this.$previewContainer );

	// Panel
	this.indexLayout = new OO.ui.IndexLayout( {
		expanded: false,
		classes: [ 'ext-edusharing-dialog-indexLayout' ]
	} );
	this.panel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true
	} );

	// Buttons & Fields
	this.repoButton = new OO.ui.ButtonWidget( {
		classes: [ 'ext-edusharing-dialog-repo-button' ],
		label: ve.msg( 'visualeditor-mwedusharingdialog-select' ),
		flags: [
			'primary',
			'progressive'
		]
	} );

	this.id = new OO.ui.TextInputWidget( {} );
	this.idField = new OO.ui.FieldLayout( this.id, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-id' )
	} ).toggle( false );

	this.previewUrl = new OO.ui.TextInputWidget( {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-previewurl' )
	} ).toggle( false );

	this.caption = new OO.ui.TextInputWidget( {} );
	this.captionField = new OO.ui.FieldLayout( this.caption, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-caption' )
	} );

	this.mediatype = new OO.ui.TextInputWidget( {} );
	this.mediatypeField = new OO.ui.FieldLayout( this.mediatype, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-mediatype' )
	} ).toggle( false );

	this.mimetype = new OO.ui.TextInputWidget( {} );
	this.mimetypeField = new OO.ui.FieldLayout( this.mimetype, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-mimetype' )
	} ).toggle( false );

	this.version = new OO.ui.TextInputWidget( {} );
	this.versionField = new OO.ui.FieldLayout( this.version, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-version' )
	} ).toggle( false );

	this.repotype = new OO.ui.TextInputWidget( {} );
	this.repotypeField = new OO.ui.FieldLayout( this.repotype, {
		align: 'left',
		label: ve.msg( 'visualeditor-mwedusharingdialog-repotype' )
	} ).toggle( false );

	this.versionshow = new OO.ui.RadioSelectInputWidget( {
		options: [
			{ data: 'latest', label: mw.msg( 'visualeditor-mwedusharingdialog-versionshow-latest' ) },
			{ data: 'current', label: mw.msg( 'visualeditor-mwedusharingdialog-versionshow-current' ) }
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

	this.panel.$element.append(
		this.$previewContainer.append(this.repoButton.$element),
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
	);

	this.repoButton.$element.on( 'click', () => {
		openRepo();
	} );

	if ( this.selectedNode ) {
		this.updatePreview();
	}
};

/**
 * Opens the EduSharing repository in a new window.
 *
 * @method
 */
function openRepo() {
	const configuredRepoUrl = mw.config.get( 'edugui' );
	const hasConfiguredRepoUrl = configuredRepoUrl &&
		configuredRepoUrl !== 'null' &&
		configuredRepoUrl.indexOf( '/null' ) === -1;
	const repoUrl = hasConfiguredRepoUrl ?
		configuredRepoUrl :
		mw.util.getUrl( 'Special:EduProxy', { edupath: 'components/search' } );
	repositoryWindow = window.open( repoUrl );
}

/**
 * Updates the preview of the EduSharing content.
 *
 * @method
 */
ve.ui.MWEduSharingDialog.prototype.updatePreview = function () {
	if ( !this.selectedNode ) {
		this.$edusharing.empty();
		return;
	}

	const mwData = this.selectedNode.getAttribute( 'mw' );
	const url = getPreviewUrl( mwData.attrs.id, mwData.attrs.previewUrl );
	renderPreview( this.$edusharing, url, mwData.body.extsrc );
};

/**
 * Handles change events on the dimensions widget.
 *
 * @method
 */
ve.ui.MWEduSharingDialog.prototype.onDimensionsChange = function () {
	this.updateActions();
};

/**
 * Inserts or updates the node in the document.
 *
 * @method
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
 * Updates the MW data for the EduSharing node.
 *
 * @method
 * @param {Object} mwData MW data object
 */
ve.ui.MWEduSharingDialog.prototype.updateMwData = function ( mwData ) {
	this.indexLayout.setTabPanel( 'options' );

	const id = this.id.getValue(),
		caption = this.caption.getValue(),
		mediatype = this.mediatype.getValue(),
		mimetype = this.mimetype.getValue(),
		dimensions = this.dimensions.getDimensions(),
		version = this.version.getValue(),
		repotype = this.repotype.getValue(),
		versionshow = this.versionshow.getValue(),
		previewUrl = this.previewUrl.getValue();

	// Parent method
	ve.ui.MWEduSharingDialog.super.prototype.updateMwData.call( this, mwData );

	// Set the EduSharing tag attributes
	mwData.attrs.action = 'new'; // Always set action to 'new' to get a new resource ID
	mwData.body.extsrc = caption;
	mwData.attrs.id = id.toString();
	mwData.attrs.mediatype = mediatype.toString();
	mwData.attrs.mimetype = mimetype.toString();
	mwData.attrs.version = version.toString();
	mwData.attrs.repotype = repotype.toString();
	mwData.attrs.versionshow = versionshow.toString();
	mwData.attrs.width = dimensions.width.toString();
	mwData.attrs.previewUrl = previewUrl.toString();
	if ( isNaN( dimensions.height ) || dimensions.height === '' || dimensions.height === '0' ) {
		mwData.attrs.height = 'auto';
	} else {
		mwData.attrs.height = dimensions.height.toString();
	}
	mwData.attrs.float = this.align.findSelectedItem().getData(); // EduSharing tag uses float, VE uses align
	// If updating an EduSharing media, delete the resourceid tag attribute to get a new resource ID
	if ( Object.prototype.hasOwnProperty.call( mwData.attrs, 'resourceid' ) ) {
		delete mwData.attrs.resourceid;
	}
};

/**
 * Determines the type of the EduSharing content (e.g., image, video, audio, textlike).
 *
 * @method
 * @param {Object} data Data object
 * @return {string} Type of the content
 */
ve.ui.MWEduSharingDialog.prototype.getTypeSwitchHelper = function ( data ) {
	let elementtype, repotype, typeSwitchHelper;
	if ( data.mediatype !== undefined && data.mediatype !== '' ) {
		elementtype = data.mediatype;
	} else if ( data.mimetype !== undefined && data.mimetype !== '' ) { // For backward compatibility
		elementtype = data.mimetype;
	} else {
		elementtype = '';
	}

	if ( data.repotype !== undefined && data.repotype !== '' ) { // Existing object
		repotype = data.repotype;
	}
	if ( data.repositoryType !== undefined && data.repositoryType !== '' ) { // Object received from iframe
		repotype = data.repositoryType;
	}

	if ( elementtype.indexOf( 'image' ) !== -1 ) {
		typeSwitchHelper = 'image';
	} else if ( elementtype.indexOf( 'audio' ) !== -1 ) {
		typeSwitchHelper = 'audio';
	} else if ( elementtype.indexOf( 'video' ) !== -1 || ( repotype && repotype.indexOf( 'YOUTUBE' ) !== -1 ) ) {
		typeSwitchHelper = 'video';
	} else {
		typeSwitchHelper = 'textlike';
	}

	return typeSwitchHelper;
};

/**
 * Sets up the dialog process.
 *
 * @method
 * @param {Object} data Data object
 * @return {OO.ui.Process} Setup process
 */
ve.ui.MWEduSharingDialog.prototype.getSetupProcess = function ( data ) {
	return ve.ui.MWEduSharingDialog.super.prototype.getSetupProcess.call( this, data )
		.next( function () {
			const mwAttrs = this.selectedNode && this.selectedNode.getAttribute( 'mw' ).attrs || {},
				mwBody = this.selectedNode && this.selectedNode.getAttribute( 'mw' ).body || {},
				isReadOnly = this.isReadOnly();
			let node;

			// Receive data from iframe
			this.repoMessageHandler = ( event ) => {
				if ( event.data && event.data.event === 'APPLY_NODE' ) {
					node = event.data.data;

					const sourceWindow = event.source || repositoryWindow;
					if ( sourceWindow && !sourceWindow.closed ) {
						sourceWindow.close();
					}
					repositoryWindow = null;
					window.focus();

					// Set the new values
					this.id.setValue( node.ref.id );
					this.caption.setValue( node.title );
					this.mediatype.setValue( node.mediatype );
					this.mimetype.setValue( node.mimetype );
					this.version.setValue( node.content.version );
					this.repotype.setValue( node.repositoryType );
					const repositoryPreview = node.preview && node.preview.url;
					this.previewUrl.setValue( repositoryPreview || '' );
					renderPreview(
						this.$edusharing,
						getPreviewUrl( node.ref.id, repositoryPreview ),
						node.title
					);

					window.removeEventListener( 'message', this.repoMessageHandler, false );
					this.repoMessageHandler = null;
				}
			};
			window.addEventListener( 'message', this.repoMessageHandler, false );

			// Initialization
			if ( this.selectedNode ) {
				this.scalable = this.selectedNode.getScalable();
				this.repoButton.setLabel( ve.msg( 'visualeditor-mwedusharingdialog-change' ) );
			} else {
				this.scalable = ve.dm.MWEduSharingNode.static.createScalable( { width: 400, height: 300 } );
				this.repoButton.setLabel( ve.msg( 'visualeditor-mwedusharingdialog-select' ) );
				this.$edusharing.html( '' );
			}

			// Set the field values
			this.id.setValue( mwAttrs.id ).setDisabled( isReadOnly );
			this.caption.setValue( mwBody.extsrc ).setDisabled( isReadOnly );
			this.mediatype.setValue( mwAttrs.mediatype ).setDisabled( isReadOnly );
			this.mimetype.setValue( mwAttrs.mimetype ).setDisabled( isReadOnly );
			this.version.setValue( mwAttrs.version ).setDisabled( isReadOnly );
			this.repotype.setValue( mwAttrs.repotype ).setDisabled( isReadOnly );
			this.versionshow.setValue( mwAttrs.versionshow ).setDisabled( isReadOnly );
			this.dimensions.setDimensions( this.scalable.getCurrentDimensions() ).setReadOnly( isReadOnly );

			// Update preview if a node is selected
			if ( this.selectedNode ) {
				this.updatePreview();
			}

			// Align widget
			this.align.selectItemByData( mwAttrs.float || 'right' ).setDisabled( isReadOnly );

			// Connect events
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
 * Tears down the dialog process.
 *
 * @method
 * @param {Object} data Data object
 * @return {OO.ui.Process} Teardown process
 */
ve.ui.MWEduSharingDialog.prototype.getTeardownProcess = function ( data ) {
	return ve.ui.MWEduSharingDialog.super.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			if ( this.repoMessageHandler ) {
				window.removeEventListener( 'message', this.repoMessageHandler, false );
				this.repoMessageHandler = null;
			}
			// Disconnect events
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

/**
 * Returns the height of the dialog body.
 *
 * @method
 * @return {number} Body height
 */
ve.ui.MWEduSharingDialog.prototype.getBodyHeight = function () {
	return 700;
};

// Registration
ve.ui.windowFactory.register( ve.ui.MWEduSharingDialog );
