/*!
 * VisualEditor MWEduSharingNode tests.
 */

QUnit.module('ext.edusharing.visualEditor');

(function () {
	'use strict';

	/* Sample specs */

	var sampleSpecs = {
		areaEduSharing: {
			version: 2,
			width: 500,
			height: 200,
			eduVersion: {
				top: 10,
				left: 30,
				bottom: 30,
				right: 10
			},
			data: [
				{
					name: 'table',
					values: [
						{ x: 0, y: 28 },
						{ x: 1, y: 43 },
						{ x: 2, y: 81 },
						{ x: 3, y: 19 }
					]
				}
			],
			scales: [
				{
					name: 'x',
					type: 'linear',
					range: 'width',
					zero: false,
					domain: {
						data: 'table',
						field: 'x'
					}
				},
				{
					name: 'y',
					type: 'linear',
					range: 'height',
					nice: true,
					domain: {
						data: 'table',
						field: 'y'
					}
				}
			],
			axes: [
				{
					type: 'x',
					scale: 'x'
				},
				{
					type: 'y',
					scale: 'y'
				}
			],
			marks: [
				{
					type: 'area',
					from: {
						data: 'table'
					},
					properties: {
						enter: {
							interpolate: {
								value: 'monotone'
							},
							x: {
								scale: 'x',
								field: 'x'
							},
							y: {
								scale: 'y',
								field: 'y'
							},
							y2: {
								scale: 'y',
								value: 0
							},
							fill: {
								value: 'steelblue'
							}
						}
					}
				}
			]
		},

		stackedAreaEduSharing: {
			version: 2,
			width: 500,
			height: 200,
			eduVersion: {
				top: 10,
				left: 30,
				bottom: 30,
				right: 10
			},
			data: [
				{
					name: 'table',
					values: [
						{ x: 0, y: 28, c: 0 },
						{ x: 0, y: 55, c: 1 },
						{ x: 1, y: 43, c: 0 },
						{ x: 1, y: 91, c: 1 },
						{ x: 2, y: 81, c: 0 },
						{ x: 2, y: 53, c: 1 },
						{ x: 3, y: 19, c: 0 },
						{ x: 3, y: 87, c: 1 },
						{ x: 4, y: 52, c: 0 },
						{ x: 4, y: 48, c: 1 },
						{ x: 5, y: 24, c: 0 },
						{ x: 5, y: 49, c: 1 },
						{ x: 6, y: 87, c: 0 },
						{ x: 6, y: 66, c: 1 },
						{ x: 7, y: 17, c: 0 },
						{ x: 7, y: 27, c: 1 },
						{ x: 8, y: 68, c: 0 },
						{ x: 8, y: 16, c: 1 },
						{ x: 9, y: 49, c: 0 },
						{ x: 9, y: 15, c: 1 }
					]
				},
				{
					name: 'stats',
					source: 'table',
					transform: [
						{
							type: 'facet',
							keys: [
								'x'
							]
						},
						{
							type: 'stats',
							value: 'y'
						}
					]
				}
			],
			scales: [
				{
					name: 'x',
					type: 'linear',
					range: 'width',
					zero: false,
					domain: {
						data: 'table',
						field: 'x'
					}
				},
				{
					name: 'y',
					type: 'linear',
					range: 'height',
					nice: true,
					domain: {
						data: 'stats',
						field: 'sum'
					}
				},
				{
					name: 'color',
					type: 'ordinal',
					range: 'category10'
				}
			],
			axes: [
				{
					type: 'x',
					scale: 'x'
				},
				{
					type: 'y',
					scale: 'y'
				}
			],
			marks: [
				{
					type: 'group',
					from: {
						data: 'table',
						transform: [
							{
								type: 'facet',
								keys: [
									'c'
								]
							},
							{
								type: 'stack',
								point: 'x',
								height: 'y'
							}
						]
					},
					marks: [
						{
							type: 'area',
							properties: {
								enter: {
									interpolate: {
										value: 'monotone'
									},
									x: {
										scale: 'x',
										field: 'x'
									},
									y: {
										scale: 'y',
										field: 'y'
									},
									y2: {
										scale: 'y',
										field: 'y2'
									},
									fill: {
										scale: 'color',
										field: 'c'
									}
								},
								update: {
									fillOpacity: {
										value: 1
									}
								},
								hover: {
									fillOpacity: {
										value: 0.5
									}
								}
							}
						}
					]
				}
			]
		},

		invalidAxesBarEduSharing: {
			version: 2,
			width: 500,
			height: 200,
			eduVersion: {
				top: 10,
				left: 30,
				bottom: 30,
				right: 10
			},
			data: [
				{
					name: 'table',
					values: [
						{ x: 0, y: 28 },
						{ x: 1, y: 43 },
						{ x: 2, y: 81 },
						{ x: 3, y: 19 }
					]
				}
			],
			scales: [
				{
					name: 'x',
					type: 'linear',
					range: 'width',
					zero: false,
					domain: {
						data: 'table',
						field: 'x'
					}
				},
				{
					name: 'y',
					type: 'linear',
					range: 'height',
					nice: true,
					domain: {
						data: 'table',
						field: 'y'
					}
				}
			],
			axes: [
				{
					type: 'x',
					scale: 'z'
				},
				{
					type: 'y',
					scale: 'y'
				}
			],
			marks: [
				{
					type: 'area',
					from: {
						data: 'table'
					},
					properties: {
						enter: {
							interpolate: {
								value: 'monotone'
							},
							x: {
								scale: 'x',
								field: 'x'
							},
							y: {
								scale: 'y',
								field: 'y'
							},
							y2: {
								scale: 'y',
								value: 0
							},
							fill: {
								value: 'steelblue'
							}
						}
					}
				}
			]
		}
	};

	/* Tests */

	QUnit.test('ve.dm.MWEduSharingNode', function (assert) {
		var node = new ve.dm.MWEduSharingNode(),
			specString = JSON.stringify(sampleSpecs.areaEduSharing);

		assert.deepEqual(node.getSpec(), ve.dm.MWEduSharingNode.static.defaultSpec, 'MWEduSharingNode spec is initialized to the default spec');

		node.setSpecFromString(specString);
		assert.deepEqual(node.getSpec(), sampleSpecs.areaEduSharing, 'Basic valid spec is parsed');

		node.setSpecFromString('invalid JSON string');
		assert.deepEqual(node.getSpec(), {}, 'Setting an invalid JSON resets the spec to an empty object');

		node.setSpec(sampleSpecs.stackedAreaEduSharing);
		assert.deepEqual(node.getSpec(), sampleSpecs.stackedAreaEduSharing, 'Setting the spec by object');

		node.setSpec(null);
		assert.deepEqual(node.getSpec(), {}, 'Setting a null spec resets the spec to an empty object');
	});

	QUnit.test('ve.ce.MWEduSharingNode', function (assert) {
		var view = ve.test.utils.createSurfaceViewFromHtml(
			'<div typeof="mw:Extension/edusharing"></div>'
		),
			documentNode = view.getDocument().getDocumentNode(),
			node = documentNode.children[0];

		assert.strictEqual(node.type, 'mwEduSharing', 'Parsoid HTML edusharings are properly recognized as edusharing nodes');
	});

	QUnit.test('ve.ce.MWEduSharingNode.static', function (assert) {
		var testElement = document.createElement('div'),
			promise,
			renderValidTest = assert.async(),
			renderInvalidTest = assert.async();

		$('#qunit-fixture').append(testElement);

		promise = ve.ce.MWEduSharingNode.static.vegaParseSpec(sampleSpecs.areaEduSharing, testElement);
		promise.always(function () {
			assert.strictEqual(promise.state(), 'resolved', 'Single edusharing gets rendered correctly');
			renderValidTest();
		});

		ve.ce.MWEduSharingNode.static.vegaParseSpec(
			sampleSpecs.invalidAxesBarEduSharing, testElement
		).always(
			function (failMessageKey) {
				assert.strictEqual(failMessageKey, 'edusharing-ve-vega-error', 'Invalid edusharing triggers an error at rendering');
				renderInvalidTest();
			}
		);
	});

	QUnit.test('ve.dm.MWEduSharingModel', function (assert) {
		var model = new ve.dm.MWEduSharingModel(sampleSpecs.areaEduSharing),
			updateSpecRemoval = {
				marks: undefined,
				scales: undefined,
				eduVersion: { top: 50 },
				axes: [
					{ type: 'z' }
				]
			},
			areaEduSharingRemovalExpected = {
				version: 2,
				width: 500,
				height: 200,
				eduVersion: {
					top: 50,
					left: 30,
					bottom: 30,
					right: 10
				},
				data: [
					{
						name: 'table',
						values: [
							{ x: 0, y: 28 },
							{ x: 1, y: 43 },
							{ x: 2, y: 81 },
							{ x: 3, y: 19 }
						]
					}
				],
				axes: [
					{
						type: 'z',
						scale: 'x'
					},
					{
						type: 'y',
						scale: 'y'
					}
				]
			};

		assert.strictEqual(model.hasBeenChanged(), false, 'Model changes are correctly initialized');

		model.setSpecFromString('invalid json string');
		assert.strictEqual(model.hasBeenChanged(), true, 'Model spec resets to an empty object when fed invalid data');

		model.setSpecFromString(JSON.stringify(sampleSpecs.areaEduSharing, null, '\t'));
		assert.strictEqual(model.hasBeenChanged(), false, 'Model doesn\'t throw false positives after applying no changes');

		model.setSpecFromString(JSON.stringify(sampleSpecs.stackedAreaEduSharing));
		assert.strictEqual(model.hasBeenChanged(), true, 'Model recognizes valid changes to spec');

		model.setSpecFromString(JSON.stringify(sampleSpecs.areaEduSharing));
		model.updateSpec(updateSpecRemoval);
		assert.deepEqual(model.getSpec(), areaEduSharingRemovalExpected, 'Updating the spec and removing properties');
	});

	QUnit.test('ve.dm.MWEduSharingModel.static', function (assert) {
		var result,
			basicTestObj = {
				a: 3,
				b: undefined,
				c: {
					ca: undefined,
					cb: 'undefined'
				}
			},
			complexTestObj = {
				a: {
					aa: undefined,
					ab: 3,
					ac: [
						{
							ac0a: undefined,
							ac0b: 4
						},
						{
							ac1a: 'ac1a',
							ac1b: 5,
							ac1c: undefined
						}
					]
				},
				b: {
					a: undefined,
					b: undefined,
					c: 2
				},
				c: 3,
				d: undefined
			},
			undefinedPropertiesBasicExpected = ['b', 'c.ca'],
			undefinedPropertiesComplexExpected = ['a.aa', 'a.ac.0.ac0a', 'a.ac.1.ac1c', 'b.a', 'b.b', 'd'],
			removePropBasicExpected = {
				a: 3,
				b: undefined,
				c: {
					cb: 'undefined'
				}
			},
			removePropComplexExpected = {
				a: {
					aa: undefined,
					ab: 3,
					ac: [{
						ac1b: 5,
						ac1c: undefined
					}]
				},
				c: 3,
				d: undefined
			};

		result = ve.dm.MWEduSharingModel.static.getUndefinedProperties(basicTestObj);
		assert.deepEqual(result, undefinedPropertiesBasicExpected, 'Basic deep undefined property scan is successful');

		result = ve.dm.MWEduSharingModel.static.getUndefinedProperties(complexTestObj);
		assert.deepEqual(result, undefinedPropertiesComplexExpected, 'Complex deep undefined property scan is successful');

		result = ve.dm.MWEduSharingModel.static.removeProperty(basicTestObj, ['c', 'ca']);
		assert.deepEqual(basicTestObj, removePropBasicExpected, 'Basic nested property removal is successful');

		ve.dm.MWEduSharingModel.static.removeProperty(complexTestObj, ['a', 'ac', '0']);
		ve.dm.MWEduSharingModel.static.removeProperty(complexTestObj, ['a', 'ac', '0', 'ac1a']);
		ve.dm.MWEduSharingModel.static.removeProperty(complexTestObj, ['b']);
		assert.deepEqual(complexTestObj, removePropComplexExpected, 'Complex nested property removal is successful');

		assert.throws(
			ve.dm.MWEduSharingModel.static.removeProperty(complexTestObj, ['b']),
			'Trying to delete an invalid property throws an error'
		);
	});
}());
