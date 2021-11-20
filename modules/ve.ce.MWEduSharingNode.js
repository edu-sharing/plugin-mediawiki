/*!
 * VisualEditor ContentEditable MWEduSharingNode class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see http://ve.mit-license.org
 */
/**
 * ContentEditable paragraph node.
 *
 * @class
 * @extends ve.ce.MWBlockExtensionNode
 * @mixins ve.ce.ResizableNode
 *
 * @constructor
 * @param {ve.dm.MWEduSharingNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
 ve.ce.MWEduSharingNode = function VeCeMWEduSharing( model, config ) {
	config = config || {};
	var typeSwitchHelper;
	
	this.$thumbinner = $( '<div>' ).addClass( 'thumbinner' );
	this.$edusharing = $( '<div>' ).addClass( 'mw-edusharing-preview' );
	this.$caption = $( '<div>' ).addClass( 'mw-edusharing-caption' );

	// Parent constructor
	ve.ce.MWEduSharingNode.super.apply( this, arguments );

	// Mixin constructors
	typeSwitchHelper = this.model.getTypeSwitchHelper();
	if ( typeSwitchHelper === 'image' || typeSwitchHelper === 'video' ){
		ve.ce.ResizableNode.call( this, this.$edusharing, config );
	}

	this.$imageLoader = null;

	// Events
	
	this.model.connect( this, { attributeChange: 'onAttributeChange' } );

	this.$caption.html( this.requiresInteractive() );

	// DOM changes
	this.$element
		.empty()
		.addClass( 've-ce-mwEduSharingNode mw-edusharing-container mw-edusharing-' + typeSwitchHelper + ' thumb' )
		.append(
			this.$thumbinner.append(
				this.$edusharing, this.$caption
			)
		);

};

/* Inheritance */

OO.inheritClass( ve.ce.MWEduSharingNode, ve.ce.MWBlockExtensionNode );

OO.mixinClass( ve.ce.MWEduSharingNode, ve.ce.ResizableNode );

/* Static Properties */

ve.ce.MWEduSharingNode.static.name = 'mwEduSharing';

ve.ce.MWEduSharingNode.static.tagName = 'div';

ve.ce.MWEduSharingNode.static.primaryCommandName = 'mwEduSharing';

/* Methods */

/**
  * @return {boolean} EduSharing requires interactive rendering
 */
ve.ce.MWEduSharingNode.prototype.requiresInteractive = function () {
	var mwData = this.model.getAttribute( 'mw' );
	return ( mwData.body && mwData.body.extsrc );
};

/**
 * Update the rendering of the 'align', src', 'width' and 'height' attributes
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
 * @inheritdoc
 */
ve.ce.MWEduSharingNode.prototype.onSetup = function () {
	ve.ce.MWEduSharingNode.super.prototype.onSetup.call( this );
	this.update();
};

/**
 * Update the edusharing rendering
 */
ve.ce.MWEduSharingNode.prototype.update = function () {
	var requiresInteractive = this.requiresInteractive(),
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

		// this.updateStatic( this.model.getCurrentDimensions().width, this.model.getCurrentDimensions().height );
		this.updateStatic();
	}

	// Classes documented in removeClass
	this.$element
		.removeClass( 'tleft tnone center tright' )
		.addClass( alignClasses[ align ] );
	this.$edusharing
		.css( this.model.getCurrentDimensions() );
	this.$thumbinner
		.css({
			'width' : '100%',	
			'max-width' : this.model.getCurrentDimensions().width,
			'height' : 'auto'
		});
};

/**
 * Update the static rendering
 */
ve.ce.MWEduSharingNode.prototype.updateStatic = function () {
	var url, node = this, typeSwitchHelper;
	if ( !this.model.getCurrentDimensions().width ) {
		return;
	}

	if ( this.$imageLoader ) {
		this.$imageLoader.off();
		this.$imageLoader = null;
	}

	url = this.model.getUrl();
	typeSwitchHelper = this.model.getTypeSwitchHelper();

	if ( typeSwitchHelper !== 'textlike'){
		this.$imageLoader = $( '<img>' ).on( 'load', function () {
			node.$edusharing.html( '<img src="' + url + '" alt="" style="width: 100%; height: auto; "/>' );
		} ).attr( 'src', url );
	} else {
		node.$edusharing.html('');
	}

	$caption = this.requiresInteractive();
	node.$caption.html( $caption );

};

/**
 * @inheritdoc ve.ce.ResizableNode
 */
ve.ce.MWEduSharingNode.prototype.onResizableResizing = function () {
	// Mixin method
	ve.ce.ResizableNode.prototype.onResizableResizing.apply( this, arguments );
};

/**
 * @inheritdoc ve.ce.ResizableNode
 */
// ve.ce.MWEduSharingNode.prototype.getAttributeChanges = function ( width, height ) {
	ve.ce.MWEduSharingNode.prototype.getAttributeChanges = function ( width ) {
	var mwData = ve.copy( this.model.getAttribute( 'mw' ) );

	mwData.attrs.width = width.toString();
	// mwData.attrs.height = height.toString();
	mwData.attrs.height = 'auto';

	return { mw: mwData };
};

/* Registration */

ve.ce.nodeFactory.register( ve.ce.MWEduSharingNode );
