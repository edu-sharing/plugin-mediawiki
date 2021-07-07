/*!
 * VisualEditor DataModel MWEduSharingNode class.
 *
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * DataModel MediaWiki edusharing node.
 *
 * @class
 * @extends ve.dm.MWBlockExtensionNode
 * @mixins ve.dm.ResizableNode
 *
 * @constructor
 * @param {Object} [element]
 */
ve.dm.MWEduSharingNode = function VeDmMWEduSharingNode() {
	var mw, extsrc;

	// Parent constructor
	ve.dm.MWEduSharingNode.super.apply(this, arguments);

	// Mixin constructors
	ve.dm.ResizableNode.call(this);

	// Properties
	this.spec = null;

	// Events
	this.connect(this, {
		attributeChange: 'onAttributeChange'
	});

	// Initialize specificiation
	mw = this.getAttribute('mw');
	this.setSpecFromNode(mw);

};

/* Inheritance */

OO.inheritClass(ve.dm.MWEduSharingNode, ve.dm.MWBlockExtensionNode);
OO.mixinClass(ve.dm.MWEduSharingNode, ve.dm.ResizableNode);

/* Static Members */

ve.dm.MWEduSharingNode.static.name = 'mwEduSharing';

ve.dm.MWEduSharingNode.static.extensionName = 'edusharing';


/* Methods */

/**
 * @inheritdoc
 */
ve.dm.MWEduSharingNode.prototype.createScalable = function () {
	var width = ve.getProp(this.spec, 'width'),
		height = ve.getProp(this.spec, 'height');

	return new ve.dm.Scalable({
		currentDimensions: {
			width: width,
			height: height
		},
		minDimensions: ve.dm.MWEduSharingModel.static.minDimensions,
		fixedRatio: false
	});
};

/**
 * Get the specification string
 *
 * @return {string} The specification JSON string
 */
ve.dm.MWEduSharingNode.prototype.getSpecString = function () {
	return this.constructor.static.stringifySpec(this.spec);
};

/**
 * Get the parsed JSON specification
 *
 * @return {Object} The specification object
 */
ve.dm.MWEduSharingNode.prototype.getSpec = function () {
	return this.spec;
};

/**
 * Set the specificiation
 *
 * @param {Object} spec The new spec
 */
ve.dm.MWEduSharingNode.prototype.setSpec = function (spec) {
	// Consolidate all falsy values to an empty object for consistency
	this.spec = spec || {};
};

/**
 * Set the specification from a stringified version
 *
 * @param {string} str The new specification JSON string
 */
ve.dm.MWEduSharingNode.prototype.setSpecFromNode = function (mw) {
	var caption = ve.getProp(mw, 'body', 'extsrc');
	var attrs = mw.attrs;
	var spec = {
		caption: caption,
		id: attrs.id,
		action: attrs.action,
		width: attrs.width,
		height: attrs.height,
		mimetype: attrs.mimetype,
		repotype: attrs.repotype,
		float: attrs.float,
		version: attrs.version,
		versionshow: attrs.versionshow,
		resourceid: attrs.resourceid
	}
	this.setSpec(spec);
};

/**
 * React to node attribute changes
 *
 * @param {string} attributeName The attribute being updated
 * @param {Object} from The old value of the attribute
 * @param {Object} to The new value of the attribute
 */
ve.dm.MWEduSharingNode.prototype.onAttributeChange = function (attributeName, from, to) {

	console.log(attributeName, from, to,)
	if (attributeName === 'mw') {
		this.setSpecFromNode(to);
	}
};

/* Registration */

ve.dm.modelRegistry.register(ve.dm.MWEduSharingNode);
