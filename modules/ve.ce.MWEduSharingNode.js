/*!
 * VisualEditor ContentEditable MWEduSharingNode class.
 *
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * ContentEditable MediaWiki edusharing node.
 *
 * @class
 * @extends ve.ce.MWBlockExtensionNode
 * @mixins ve.ce.MWResizableNode
 *
 * @constructor
 * @param {ve.dm.MWEduSharingNode} model Model to observe
 * @param {Object} [config] Configuration options
 */
ve.ce.MWEduSharingNode = function VeCeMWEduSharingNode(model, config) {
	this.$edusharing = $('<div>').addClass('mw-edusharing');
	this.$plot = $('<div>').addClass('ve-ce-mwEduSharingNode-plot');

	console.log("########xcvxcvxcv#######", model)
	// Parent constructor
	ve.ce.MWEduSharingNode.super.apply(this, arguments);

	// Mixin constructors
	ve.ce.MWResizableNode.call(this, this.$plot, config);



	this.$element
		.addClass('mw-edusharing-container')
		.append(this.$edusharing);

	this.showHandles(['se']);
};

/* Inheritance */

OO.inheritClass(ve.ce.MWEduSharingNode, ve.ce.MWBlockExtensionNode);

// Need to mix in the base class as well
OO.mixinClass(ve.ce.MWEduSharingNode, ve.ce.ResizableNode);

OO.mixinClass(ve.ce.MWEduSharingNode, ve.ce.MWResizableNode);

/* Static Properties */

ve.ce.MWEduSharingNode.static.name = 'mwEduSharing';

ve.ce.MWEduSharingNode.static.primaryCommandName = 'edusharing';

ve.ce.MWEduSharingNode.static.tagName = 'div';

/* Static Methods */

/* Methods */
/**
 * Render a Vega edusharing inside the node
 */
ve.ce.MWEduSharingNode.prototype.update = function () {
	var node = this;

	// Clear element
	this.$edusharing.empty();
	var spec = node.getModel().getSpec()

	var plot = $("<div><h2>" + spec.caption + "</h2></div>")
	console.log("spec", spec);
	console.log("mw.config.values", mw.config.values);

	const regex = /ccrep:\/\/(.*)\/(.*)/gm;
	const match = regex.exec(spec.id);
	var repid = match[1];	
	var oid = match[2];
	console.log("match", oid, repid);
	console.log("match", match);
	var params = {
		oid: oid,
		appid:  mw.config.values.eduappid,
		repid: repid,
		resid: spec.resourceid,
		height: spec.height,
		width: spec.width,
		mime: spec.mimetype,
		// pid: spec.caption,
		// SID: mw.config.values.edusid,
		printTitle: spec.caption,
		language: "de",
	};	
	var query = Object.keys(params)
		.map(k => encodeURIComponent(k) + '=' + encodeURIComponent(params[k]))
		.join('&');
		const url = "/extensions/EduSharing/proxy.php?"+query;
		console.log(url)
	fetch(url).then(response=>{
		return response.text().then(text => {
			plot.html(text)
		});
  	});
	node.$edusharing.append(plot);
};

/**
 * @inheritdoc
 */
ve.ce.MWEduSharingNode.prototype.getAttributeChanges = function (width, height) {
	var attrChanges = {},
		newSpec = ve.dm.MWEduSharingModel.static.updateSpec(this.getModel().getSpec(), {
			width: width,
			height: height
		});

	ve.setProp(attrChanges, 'mw', 'body', 'extsrc', JSON.stringify(newSpec));

	return attrChanges;
};

/**
 * @inheritdoc
 */
ve.ce.MWEduSharingNode.prototype.getFocusableElement = function () {
	return this.$edusharing;
};

/* Registration */

ve.ce.nodeFactory.register(ve.ce.MWEduSharingNode);
