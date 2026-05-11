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
 * ContentEditable paragraph node for EduSharing content.
 *
 * @class ve.ce.MWEduSharingNode
 * @extends ve.ce.MWBlockExtensionNode
 * @mixes ve.ce.ResizableNode
 *
 * @constructor
 * @param {ve.dm.MWEduSharingNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWEduSharingNode = function VeCeMWEduSharing( model, config ) {
	config = config || {};

	// Classes: thumbinner, ext-edusharing-preview
	this.$thumbinner = $( '<div>' ).addClass( 'thumbinner' );
	this.$edusharing = $( '<div>' ).addClass( 'ext-edusharing-preview' );

	// Parent constructor
	ve.ce.MWEduSharingNode.super.apply( this, arguments );

	// Mixin constructors
	const typeSwitchHelper = this.model.getTypeSwitchHelper();
	if ( typeSwitchHelper === 'image' || typeSwitchHelper === 'video' ) {
		ve.ce.ResizableNode.call( this, this.$edusharing, config );
	}

	this.$imageLoader = null;

	// Events
	this.model.connect( this, { attributeChange: 'onAttributeChange' } );

	// DOM changes
	// Classes: ve-ce-mwEduSharingNode, ext-edusharing-container, ext-edusharing-image, ext-edusharing-video, thumb
	this.$element
		.empty()
		.addClass( 've-ce-mwEduSharingNode ext-edusharing-container ext-edusharing-' + typeSwitchHelper + ' thumb' )
		.append(
			this.$thumbinner.append(
				this.$edusharing
			)
		);
};

// Inheritance
OO.inheritClass( ve.ce.MWEduSharingNode, ve.ce.MWBlockExtensionNode );
OO.mixinClass( ve.ce.MWEduSharingNode, ve.ce.ResizableNode );

// Static Properties
ve.ce.MWEduSharingNode.static.name = 'mwEduSharing';
ve.ce.MWEduSharingNode.static.tagName = 'div';
ve.ce.MWEduSharingNode.static.primaryCommandName = 'mwEduSharing';

/**
 * Checks if EduSharing content requires interactive rendering.
 *
 * @method
 * @return {boolean} True if interactive rendering is required, false otherwise
 */
ve.ce.MWEduSharingNode.prototype.requiresInteractive = function () {
	const mwData = this.model.getAttribute( 'mw' );
	return ( mwData.body && mwData.body.extsrc );
};

/**
 * Updates the rendering of the 'align', 'src', 'width', and 'height' attributes
 * when they change in the model.
 *
 * @method
 * @param {string} key Attribute key
 * @param {string} from Old value
 * @param {string} to New value
 */
ve.ce.MWEduSharingNode.prototype.onAttributeChange = function () {
	this.update();
};

/**
 * Sets up the node after it is attached to the DOM.
 *
 * @method
 */
ve.ce.MWEduSharingNode.prototype.onSetup = function () {
	ve.ce.MWEduSharingNode.super.prototype.onSetup.call( this );
	this.update();
};

/**
 * Updates the EduSharing content rendering.
 *
 * Classes: tleft, tnone, center, tright
 *
 * @method
 */
ve.ce.MWEduSharingNode.prototype.update = function () {
	const requiresInteractive = this.requiresInteractive(),
		align = ve.getProp( this.model.getAttribute( 'mw' ), 'attrs', 'float' ) ||
            ( this.model.doc.getDir() === 'ltr' ? 'right' : 'left' ),
		alignClasses = {
			left: 'tleft',
			center: 'tnone center',
			right: 'tright'
		};

	if ( !this.model ) {
		return;
	}

	if ( requiresInteractive ) {
		if ( this.edusharing ) {
			// Node was previously interactive
			this.edusharing.remove();
			this.edusharing = null;
		}
		this.updateStatic();
	}

	// Classes: tleft, tnone, center, tright
	this.$element
		.removeClass( 'tleft tnone center tright' )
		.addClass( alignClasses[ align ] );
	this.$edusharing
		.css( this.model.getCurrentDimensions() );
	this.$thumbinner
		.css( {
			width: '100%',
			'max-width': this.model.getCurrentDimensions().width,
			height: 'auto'
		} );
};

/**
 * Updates the static rendering of the EduSharing content.
 *
 * Classes: tleft, tnone, center, tright, ext-edusharing-preview-container, ext-edusharing-preview-image, ext-edusharing-preview-caption
 *
 * @method
 */
ve.ce.MWEduSharingNode.prototype.updateStatic = function () {
	const mwData = this.model.getAttribute( 'mw' );
	const fullId = mwData.attrs.id;
	const align = ve.getProp( this.model.getAttribute( 'mw' ), 'attrs', 'float' ) ||
        ( this.model.doc.getDir() === 'ltr' ? 'right' : 'left' ),
		alignClasses = {
			left: 'tleft',
			center: 'tnone center',
			right: 'tright'
		};

	// Remove old alignment classes
	// Classes: tleft, tnone, center, tright
	this.$element.removeClass( 'tleft tnone center tright' );

	// Add the new alignment class
	if ( alignClasses[ align ] ) {
		this.$element.addClass( alignClasses[ align ] );
	}

	// Display the preview image
	// Classes: ext-edusharing-preview-container, ext-edusharing-preview-image, ext-edusharing-preview-caption
	this.$edusharing.html( `
        <div class="ext-edusharing-preview-container">
            <img class="ext-edusharing-preview-image"
                 src="${ mwData.attrs.previewUrl }"
                 alt="${ mwData.body.extsrc || 'Preview' }"
                 style="max-width: 100%; height: auto;">
            <div class="ext-edusharing-preview-caption">
                ${ mwData.body.extsrc || 'EduSharing content' }
            </div>
        </div>
    ` );
};

/**
 * Handles the resizing of the node.
 *
 * @method
 */
ve.ce.MWEduSharingNode.prototype.onResizableResizing = function () {
	// Mixin method
	ve.ce.ResizableNode.prototype.onResizableResizing.apply( this, arguments );
};

/**
 * Returns the attribute changes for the node.
 *
 * @method
 * @param {number} width New width
 * @return {Object} Attribute changes
 */
ve.ce.MWEduSharingNode.prototype.getAttributeChanges = function ( width ) {
	const mwData = ve.copy( this.model.getAttribute( 'mw' ) );

	mwData.attrs.width = width.toString();
	mwData.attrs.height = 'auto';

	return { mw: mwData };
};

// Registration
ve.ce.nodeFactory.register( ve.ce.MWEduSharingNode );
