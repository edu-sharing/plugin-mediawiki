/*!
 * VisualEditor DataModel MWEduSharingModel class.
 *
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki edusharing model.
 *
 * @class
 * @mixins OO.EventEmitter
 *
 * @constructor
 * @param {Object} [spec] The Vega specification as a JSON object
 */
ve.dm.MWEduSharingModel = function VeDmMWEduSharingModel(spec) {
	// Mixin constructors
	OO.EventEmitter.call(this);

	// Properties
	this.spec = spec || { eduVersion: "latest" };
	this.originalSpec = ve.copy(this.spec);
};

/* Inheritance */

OO.mixinClass(ve.dm.MWEduSharingModel, OO.EventEmitter);

/* Static Members */

ve.dm.MWEduSharingModel.static.defaultEduVersion = "latest";

ve.dm.MWEduSharingModel.static.minDimensions = {
	width: 60,
	height: 60
};


/* Events */

/**
 * @event specChange
 *
 * Change when the JSON specification is updated
 *
 * @param {Object} The new specification
 */

/* Static Methods */

/**
 * Updates a spec with new parameters.
 *
 * @param {Object} spec The spec to update
 * @param {Object} params The new params to update.
 *  Properties set to undefined will be removed from the spec.
 * @return {Object} The new spec
 */
ve.dm.MWEduSharingModel.static.updateSpec = function (spec, params) {
	var undefinedProperty,
		undefinedProperties = ve.dm.MWEduSharingModel.static.getUndefinedProperties(params),
		i;

	// Remove undefined properties from spec
	for (i = 0; i < undefinedProperties.length; i++) {
		undefinedProperty = undefinedProperties[i].split('.');
		ve.dm.MWEduSharingModel.static.removeProperty(spec, $.extend([], undefinedProperty));
		ve.dm.MWEduSharingModel.static.removeProperty(params, $.extend([], undefinedProperty));
	}

	// Extend remaining properties
	spec = $.extend(true, {}, spec, params);

	return spec;
};

/**
 * Recursively gets all the keys to properties set to undefined in a JSON object
 *
 * @author Based on the work on Artyom Neustroev at http://stackoverflow.com/a/15690816/2055594
 * @private
 * @param {Object} obj The object to iterate
 * @param {string} [stack] The parent property of the root property of obj.
 *  Used internally for recursion.
 * @param {string[]} [list] The list of properties to return. Used internally for recursion.
 * @return {string[]} The list of properties to return.
 */
ve.dm.MWEduSharingModel.static.getUndefinedProperties = function (obj, stack, list) {
	var property;

	list = list || [];

	// Append . to the stack if it's defined
	stack = (stack === undefined) ? '' : stack + '.';

	for (property in obj) {
		if (Object.prototype.hasOwnProperty.call(obj, property)) {
			if (typeof obj[property] === 'object') {
				ve.dm.MWEduSharingModel.static.getUndefinedProperties(
					obj[property], stack + property, list
				);
			} else if (obj[property] === undefined) {
				list.push(stack + property);
			}
		}
	}

	return list;
};

/**
 * Removes a nested property from an object
 *
 * @param {Object} obj The object
 * @param {Array} prop The path of the property to remove
 */
ve.dm.MWEduSharingModel.static.removeProperty = function (obj, prop) {
	var firstProp = prop.shift();

	try {
		if (prop.length > 0) {
			ve.dm.MWEduSharingModel.static.removeProperty(obj[firstProp], prop);
		} else {
			if (Array.isArray(obj)) {
				obj.splice(parseInt(firstProp), 1);
			} else {
				delete obj[firstProp];
			}
		}
	} catch (err) {
		// We don't need to bubble errors here since hitting a missing property
		// will not exist anyway in the object anyway
	}
};

/**
 * Check if a spec currently has something in its dataset
 *
 * @param {Object} spec The spec
 * @return {boolean} The spec has some data in its dataset
 */
ve.dm.MWEduSharingModel.static.specHasData = function (spec) {
	// FIXME: Support multiple pipelines
	return !!spec.data[0].values.length;
};

/* Methods */

/**
 * Switch the edusharing to a different type
 *
 * @param {string} type Desired edusharing type. Can be either area, line or bar.
 * @fires specChange
 */
ve.dm.MWEduSharingModel.prototype.setDefaultValues = function () {
	var spec = { eduVersion: 'latest' }
	this.updateSpec(spec);
	this.emit('specChange', this.spec);
};

/**
 * Apply changes to the node
 *
 * @param {ve.dm.MWEduSharingNode} node The node to be modified
 * @param {ve.dm.Surface} surfaceModel The surface model for the document
 */
ve.dm.MWEduSharingModel.prototype.applyChanges = function (node, surfaceModel) {
	var mwData = ve.copy(node.getAttribute('mw'));

	var spec = this.getSpec();
	// Send transaction
	mwData.body.extsrc = spec.caption;
	surfaceModel.change(
		ve.dm.TransactionBuilder.static.newFromAttributeChanges(
			surfaceModel.getDocument(),
			node.getOffset(),
			{ mw: mwData }
		)
	);
	surfaceModel.applyStaging();
};

/**
 * Update the spec with new parameters
 *
 * @param {Object} params The new parameters to be updated in the spec
 * @fires specChange
 */
ve.dm.MWEduSharingModel.prototype.updateSpec = function (params) {
	var updatedSpec = ve.dm.MWEduSharingModel.static.updateSpec(
		$.extend(true, {}, this.spec), params
	);

	// Only emit a change event if the spec really changed
	if (!OO.compare(this.spec, updatedSpec)) {
		this.spec = updatedSpec;
		this.emit('specChange', this.spec);
	}
};

// /**
//  * Sets and validates the specification from a stringified version
//  *
//  * @param {string} str The new specification string
//  * @fires specChange
//  */
// ve.dm.MWEduSharingModel.prototype.setSpecFromString = function (str) {
// 	var newSpec = ve.dm.MWEduSharingNode.static.parseSpecString(str);

// 	// Only apply changes if the new spec is valid JSON and if the
// 	// spec truly was modified
// 	if (!OO.compare(this.spec, newSpec)) {
// 		this.spec = newSpec;
// 		this.emit('specChange', this.spec);
// 	}
// };

/**
 * Get the specification
 *
 * @return {Object} The specification
 */
ve.dm.MWEduSharingModel.prototype.getSpec = function () {
	return this.spec;
};

/**
 * Get the stringified specification
 *
 * @return {string} The specification string
 */
ve.dm.MWEduSharingModel.prototype.getSpecString = function () {
	return ve.dm.MWEduSharingNode.static.stringifySpec(this.spec);
};

/**
 * Get the original stringified specificiation
 *
 * @return {string} The original JSON string specification
 */
ve.dm.MWEduSharingModel.prototype.getOriginalSpecString = function () {
	return ve.dm.MWEduSharingNode.static.stringifySpec(this.originalSpec);
};


/**
 * Get edusharing size
 *
 * @return {Object} The edusharing width and height
 */
ve.dm.MWEduSharingModel.prototype.getSize = function () {
	return {
		width: this.spec.width,
		height: this.spec.height
	};
};

/**
 * Set the edusharing width
 *
 * @param {number} value The new width
 * @fires specChange
 */
ve.dm.MWEduSharingModel.prototype.setWidth = function (value) {
	this.spec.width = value;

	this.emit('specChange', this.spec);
};


/**
 * Set the edusharing caption
 *
 * @param {number} value The new width
 * @fires specChange
 */
ve.dm.MWEduSharingModel.prototype.setCaption = function (value) {
	this.spec.caption = value;
	this.emit('specChange', this.spec);
};

/**
 * Set the edusharing height
 *
 * @param {number} value The new height
 * @fires specChange
 */
ve.dm.MWEduSharingModel.prototype.setHeight = function (value) {
	this.spec.height = value;

	this.emit('specChange', this.spec);
};

/**
 * Get the eduVersion values of the edusharing
 *
 * @return {Object} The eduVersions
 */
ve.dm.MWEduSharingModel.prototype.getEduVersionObject = function () {
	return this.spec.eduVersion;
};

/**
 * Return the default eduVersion
 *
 * @return {Object} The default eduVersion values
 */
ve.dm.MWEduSharingModel.prototype.getDefaultEduVersionObject = function () {

	var i,
		indexes = ['top', 'bottom', 'left', 'right'],
		eduVersionObj = {};

	for (i = 0; i < indexes.length; i++) {
		eduVersionObj[indexes[i]] = ve.dm.MWEduSharingModel.static.defaultEduVersion;
	}

	return eduVersionObj;
};

/**
 * Set a eduVersion value
 *
 * @param {string} index The index to change. Can be either top, right, bottom or right
 * @param {number} value The new value
 * @fires specChange
 */
ve.dm.MWEduSharingModel.prototype.setEduVersion = function (index, value) {
	if (this.isEduVersionAutomatic()) {
		this.spec.eduVersion = this.getDefaultEduVersionObject();
	}

	this.spec.eduVersion[index] = value;

	this.emit('specChange', this.spec);
};


/**
 * Returns whether the current spec has been modified since the dialog was opened
 *
 * @return {boolean} The spec was changed
 */
ve.dm.MWEduSharingModel.prototype.hasBeenChanged = function () {
	return !OO.compare(this.spec, this.originalSpec);
};

/**
 * Returns whether the eduVersion is set to be automatic or not
 *
 * @return {boolean} The eduVersion is automatic
 */
ve.dm.MWEduSharingModel.prototype.isEduVersionAutomatic = function () {
	return OO.compare(this.spec.eduVersion, undefined);
};
