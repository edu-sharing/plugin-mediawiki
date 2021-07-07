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

	console.log("jdslkfjlkdf", this.$edusharing)
	var plot = $("<h2>" + spec.caption + "</h2>")
	// node.$
	node.$edusharing.append(plot);

	mw.loader.using('ext.edusharing.vega2').done(function () {
		console.log("jdslkfjlksdfdsfdsöäfk fkslödf")
		node.$plot.detach();

		node.constructor.static.vegaParseSpec(node.getModel().getSpec(), node.$edusharing[0]).then(
			function (view) {
				// HACK: We need to know which eduVersion values Vega computes in case
				// of automatic eduVersion, but it isn't properly exposed in the view				
				node.$edusharing.append(node.$plot);
				// eslint-disable-next-line no-underscore-dangle
				node.$plot.css(view._eduVersion);

				node.calculateHighlights();
			},
			function (failMessageKey) {
				// The following messages are used here:
				// * edusharing-ve-no-spec
				// * edusharing-ve-empty-edusharing
				// * edusharing-ve-vega-error-no-render
				// * edusharing-ve-vega-error
				node.$edusharing.text(ve.msg(failMessageKey));
			}
		);
	});
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
