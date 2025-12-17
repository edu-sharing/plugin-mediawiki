/**
 * VisualEditor DataModel MWEduSharingNode class
 *
 * This class provides a custom node for embedding edu-sharing content in the VisualEditor's DataModel.
 * It supports interactive and static rendering of edu-sharing resources (e.g., images, videos).
 *
 * @author   Jan Böhme <jan@idea-sketch.com>
 * @author   Uwe Schützenmeister <uwe@idea-sketch.com>
 * @license  MIT
 */

/**
 * DataModel MW EduSharing node.
 *
 * @class ve.dm.MWEduSharingNode
 * @extends ve.dm.MWBlockExtensionNode
 * @mixes ve.dm.ResizableNode
 *
 * @constructor
 * @param {Object} [element] Reference to element in linear model
 * @param {ve.dm.Node[]} [children]
 */
ve.dm.MWEduSharingNode = function VeDmMWEduSharing() {
	// Parent constructor
	ve.dm.MWEduSharingNode.super.apply( this, arguments );

	// Mixin constructors
	ve.dm.ResizableNode.call( this );
};

// Inheritance
OO.inheritClass( ve.dm.MWEduSharingNode, ve.dm.MWBlockExtensionNode );
OO.mixinClass( ve.dm.MWEduSharingNode, ve.dm.ResizableNode );

// Static Properties
ve.dm.MWEduSharingNode.static.name = 'mwEduSharing';
ve.dm.MWEduSharingNode.static.extensionName = 'edusharing';
ve.dm.MWEduSharingNode.static.matchTagNames = null; // Any tags

/**
 * Converts the node to a data element.
 *
 * @static
 * @method
 * @return {Object} Data element
 */
ve.dm.MWEduSharingNode.static.toDataElement = function () {
	const dataElement = ve.dm.MWEduSharingNode.super.static.toDataElement.apply( this, arguments );

	dataElement.attributes.width = +dataElement.attributes.mw.attrs.width;
	dataElement.attributes.height = +dataElement.attributes.mw.attrs.height;

	return dataElement;
};

/**
 * Generates the preview URL for the EduSharing content.
 *
 * @static
 * @method
 * @param {Object} dataElement Data element
 * @return {string} Preview URL
 */
ve.dm.MWEduSharingNode.static.getUrl = function ( dataElement ) {
	const mwId = dataElement.attributes.mw.attrs.id.slice( 14 ),
		previewUrl = mw.config.get( 'edupreview' );

	return previewUrl + 'nodeId=' + mwId;
};

/**
 * Creates a scalable object for the node.
 *
 * @static
 * @method
 * @param {Object} dimensions Dimensions object
 * @return {ve.dm.Scalable} Scalable object
 */
ve.dm.MWEduSharingNode.static.createScalable = function ( dimensions ) {
	return new ve.dm.Scalable( {
		fixedRatio: true,
		currentDimensions: {
			width: dimensions.width,
			height: 'auto'
		},
		minDimensions: {
			width: 480,
			height: 270
		},
		maxDimensions: {
			width: 1120,
			height: 630
		}
	} );
};

/**
 * Returns the current dimensions of the node.
 *
 * @method
 * @return {Object} Current dimensions
 */
ve.dm.MWEduSharingNode.prototype.getCurrentDimensions = function () {
	return {
		width: +this.getAttribute( 'mw' ).attrs.width,
		height: 'auto'
	};
};

/**
 * Returns the URL for the EduSharing content.
 *
 * @method
 * @param {number} width Width
 * @param {number} height Height
 * @return {string} URL
 */
ve.dm.MWEduSharingNode.prototype.getUrl = function ( width, height ) {
	return this.constructor.static.getUrl( this.element, width, height );
};

/**
 * Returns the media type of the EduSharing content.
 *
 * @method
 * @return {string} Media type
 */
ve.dm.MWEduSharingNode.prototype.getMediaType = function () {
	const mwData = this.getAttribute( 'mw' );
	return ( mwData.attrs.mediatype || mwData.attrs.mimetype ); // Return mediatype attribute if exists, otherwise return mimetype attribute
};

/**
 * Returns the repository type of the EduSharing content.
 *
 * @method
 * @return {string} Repository type
 */
ve.dm.MWEduSharingNode.prototype.getRepoType = function () {
	const mwData = this.getAttribute( 'mw' );
	return ( mwData.attrs.repotype );
};

/**
 * Determines the type of the EduSharing content (e.g., image, video, audio, textlike).
 *
 * @method
 * @return {string} Type of the content
 */
ve.dm.MWEduSharingNode.prototype.getTypeSwitchHelper = function () {
	const elementtype = this.getMediaType();
	const repotype = this.getRepoType();
	let typeSwitchHelper = '';

	if ( elementtype.indexOf( 'image' ) !== -1 ) {
		typeSwitchHelper = 'image';
	} else if ( elementtype.indexOf( 'audio' ) !== -1 ) {
		typeSwitchHelper = 'audio';
	} else if ( elementtype.indexOf( 'video' ) !== -1 || repotype.indexOf( 'YOUTUBE' ) !== -1 ) {
		typeSwitchHelper = 'video';
	} else {
		typeSwitchHelper = 'textlike';
	}

	return typeSwitchHelper;
};

/**
 * Creates a scalable object for the node.
 *
 * @method
 * @return {ve.dm.Scalable} Scalable object
 */
ve.dm.MWEduSharingNode.prototype.createScalable = function () {
	return this.constructor.static.createScalable( this.getCurrentDimensions() );
};

/**
 * Checks whether the EduSharing content contains any data.
 *
 * @method
 * @return {boolean} True if data is present, false otherwise
 */
ve.dm.MWEduSharingNode.prototype.usesEduSharingData = function () {
	const mwData = this.getAttribute( 'mw' );
	return ( mwData.body && mwData.body.extsrc );
};

// Registration
ve.dm.modelRegistry.register( ve.dm.MWEduSharingNode );
