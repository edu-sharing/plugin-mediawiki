/*!
 * VisualEditor DataModel MWEduSharingNode class.
 *
 * @copyright 2011-2015 VisualEditor Team and others; see http://ve.mit-license.org
 */

/**
 * DataModel MW EduSharing node.
 *
 * @class
 * @extends ve.dm.MWBlockExtensionNode
 * @mixins ve.dm.ResizableNode
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

/* Inheritance */

OO.inheritClass( ve.dm.MWEduSharingNode, ve.dm.MWBlockExtensionNode );

OO.mixinClass( ve.dm.MWEduSharingNode, ve.dm.ResizableNode );

/* Static Properties */

ve.dm.MWEduSharingNode.static.name = 'mwEduSharing';

ve.dm.MWEduSharingNode.static.extensionName = 'edusharing';

// ve.dm.MWEduSharingNode.static.matchTagNames = [ 'div' ];

ve.dm.MWEduSharingNode.static.matchTagNames = null; // any tags

/* Static methods */

ve.dm.MWEduSharingNode.static.toDataElement = function () {
	var dataElement = ve.dm.MWEduSharingNode.super.static.toDataElement.apply( this, arguments );

	dataElement.attributes.width = +dataElement.attributes.mw.attrs.width;
	dataElement.attributes.height = +dataElement.attributes.mw.attrs.height;

	return dataElement;
};

// ve.dm.MWEduSharingNode.static.getUrl = function ( dataElement, width, height ) {
	ve.dm.MWEduSharingNode.static.getUrl = function ( dataElement ) {
	var mwAttrs = dataElement.attributes.mw.attrs,
	mwId = dataElement.attributes.mw.attrs.id.substr(14),
	previewUrl = mw.config.get( 'edupreview' );

	return previewUrl + 'nodeId=' + mwId;
		// mwBody + ',' +
		// mwAttrs.width + ',' +
		// mwAttrs.height;
};


ve.dm.MWEduSharingNode.static.createScalable = function ( dimensions ) {
	return new ve.dm.Scalable( {
		fixedRatio: true,
		currentDimensions: {
			width: dimensions.width,
			// height: dimensions.height
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

ve.dm.MWEduSharingNode.prototype.getCurrentDimensions = function () {
	return {
		width: +this.getAttribute( 'mw' ).attrs.width,
		// height: +this.getAttribute( 'mw' ).attrs.height
		height: 'auto'
	};
};

/* Methods */

ve.dm.MWEduSharingNode.prototype.getUrl = function ( width, height ) {
	return this.constructor.static.getUrl( this.element, width, height );
};

/**
 * @inheritdoc
 */
ve.dm.MWEduSharingNode.prototype.createScalable = function () {
	return this.constructor.static.createScalable( this.getCurrentDimensions() );
};

/**
 * Checks whether the edusharing contains any data.
 *
 * @return {boolean}
 */
ve.dm.MWEduSharingNode.prototype.usesEduSharingData = function () {
	var mwData = this.getAttribute( 'mw' );
	return !!( mwData.body && mwData.body.extsrc );
};

/* Registration */

ve.dm.modelRegistry.register( ve.dm.MWEduSharingNode );
