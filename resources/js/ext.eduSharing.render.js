/**
 * EduSharing Rendering Service Integration
 *
 * This script provides functionality to load and initialize the EduSharing rendering service.
 * It dynamically injects required assets, rewrites API URLs, registers a service worker,
 * and initializes EduSharing rendering components.
 *
 * @author   Jan Böhme <jan@idea-sketch.com>
 * @author   Uwe Schützenmeister <uwe@idea-sketch.com>
 * @license  MIT
 */

( function () {
	'use strict';

	let serviceWorkerPromise = null;
	let directRendererAssigned = false;

	/**
	 * Loads EduSharing rendering assets (script and stylesheet) only once.
	 *
	 * @method loadAssetsOnce
	 * @param {Object} config Configuration object containing script and style URLs
	 * @param {Window} targetWindow Window in which the assets are loaded
	 */
	const loadAssetsOnce = ( config, targetWindow ) => {
		const targetDocument = targetWindow.document;
		if ( !targetDocument.querySelector( 'script[data-edusharing-rendering]' ) ) {
			const script = targetDocument.createElement( 'script' );
			script.type = 'module';
			script.src = config.scriptUrl;
			script.dataset.edusharingRendering = '1';
			targetDocument.head.appendChild( script );
		}
		if ( !targetDocument.querySelector( 'link[data-edusharing-rendering]' ) ) {
			const link = targetDocument.createElement( 'link' );
			link.rel = 'stylesheet';
			link.href = config.styleUrl;
			link.dataset.edusharingRendering = '1';
			targetDocument.head.appendChild( link );
		}
	};

	/**
	 * Installs URL rewrites for EduSharing API calls.
	 *
	 * @method installRewrite
	 * @param {string} apiUrl The base API URL for EduSharing
	 * @param {string} assetsBaseUrl The public base URL for EduSharing assets
	 * @param {Window} targetWindow Window whose network APIs are adapted
	 */
	const installRewrite = ( apiUrl, assetsBaseUrl, targetWindow ) => {
		if ( targetWindow.__eduSharingRewriteInstalled ) {
			return;
		}

		/**
		 * Rewrites URLs to use the configured EduSharing API URL.
		 *
		 * @method rewrite
		 * @param {string} url The URL to rewrite
		 * @return {string} The rewritten URL
		 */
		const rewrite = ( url ) => {
			try {
				const baseUrl = targetWindow.frameElement ?
					targetWindow.parent.location.href : targetWindow.location.href;
				const parsedUrl = new URL( url, baseUrl );
				if ( parsedUrl.pathname.startsWith( '/edu-sharing/rest' ) ) {
					return apiUrl + parsedUrl.pathname.replace( '/edu-sharing/rest', '' ) + parsedUrl.search;
				}
				return url;
			} catch ( e ) {
				return url;
			}
		};

		// Override fetch to rewrite URLs
		const originalFetch = targetWindow.fetch.bind( targetWindow );
		targetWindow.fetch = ( ...args ) => {
			if ( args.length ) {
				args[ 0 ] = rewrite( args[ 0 ] );
			}
			return originalFetch( ...args );
		};

		// Override XMLHttpRequest to rewrite URLs
		const originalOpen = targetWindow.XMLHttpRequest.prototype.open;
		targetWindow.XMLHttpRequest.prototype.open = function ( method, url, ...rest ) {
			url = rewrite( url );
			return originalOpen.call( this, method, url, ...rest );
		};

		// Set global environment variables for EduSharing
		targetWindow.__env = targetWindow.__env || {};
		targetWindow.__env.EDU_SHARING_API_URL = apiUrl;
		targetWindow.__env.EDU_SHARING_BASE_URL = apiUrl;
		targetWindow.__env.EDU_SHARING_REST_URL = apiUrl;
		targetWindow.__env.API_BASE_URL = apiUrl;
		targetWindow.EDU_SHARING_API_URL = apiUrl;
		targetWindow.EDU_SHARING_BASE_URL = apiUrl;
		targetWindow.EDU_SHARING_REST_URL = apiUrl;
		targetWindow.__EDUSHARING_PUBLIC_PATH__ = assetsBaseUrl;
		targetWindow.__eduSharingRewriteInstalled = true;
	};

	/**
	 * Registers the EduSharing service worker if enabled.
	 *
	 * @async
	 * @method registerServiceWorker
	 * @param {Object} config Configuration object containing service worker settings
	 */
	const registerServiceWorker = async ( config ) => {
		if ( !config.activateServiceWorker || !config.serviceWorkerUrl || !( 'serviceWorker' in navigator ) ) {
			return;
		}
		if ( serviceWorkerPromise ) {
			return serviceWorkerPromise;
		}

		serviceWorkerPromise = ( async () => {
			await navigator.serviceWorker.register( config.serviceWorkerUrl, { scope: '/' } );
			await navigator.serviceWorker.ready;

			if ( !navigator.serviceWorker.controller ) {
				await new Promise( ( resolve ) => {
					const timeout = setTimeout( resolve, 5000 );
					navigator.serviceWorker.addEventListener( 'controllerchange', () => {
						clearTimeout( timeout );
						resolve();
					}, { once: true } );
				} );
			}
		} )().catch( ( e ) => {
			serviceWorkerPromise = null;
			mw.log.warn( 'EduSharing service worker registration failed', e );
		} );

		return serviceWorkerPromise;
	};

	/**
	 * Initializes the EduSharing rendering element.
	 *
	 * @method initElement
	 * @param {HTMLElement} wrapper The wrapper element for the EduSharing component
	 * @param {Object} config Configuration object for the EduSharing component
	 * @param {Window} targetWindow Window in which the component is created
	 */
	const initElement = ( wrapper, config, targetWindow ) => {
		if ( !wrapper ) {
			return;
		}
		wrapper.innerHTML = '';
		const element = targetWindow.document.createElement( 'edu-sharing-render' );
		element.encoded_node = config.encodedNode;
		element.signature = config.signature;
		element.jwt = config.jwt;
		element.render_url = config.renderUrl;
		element.encoded_user = config.encodedUser;
		element.service_worker_url = config.serviceWorkerUrl;
		// Registration is handled above so every renderer shares the same root-scoped worker.
		element.activate_service_worker = false;
		element.target_blank = !!config.openInNewTab;
		element.assets_url = config.assetsUrl;
		element.preview_url = config.previewUrl;
		element.resource_url = config.resourceUrl;
		element.signature_algorithm = config.signatureAlgorithm;
		element.style.maxWidth = '100%';
		if ( config.width ) {
			element.style.width = config.width + 'px';
		}
		wrapper.appendChild( element );
	};

	/**
	 * Runs an additional renderer in its own JavaScript context. The upstream
	 * custom element currently starts only one rendering job per context.
	 *
	 * @param {HTMLElement} wrapper The wrapper element for the EduSharing component
	 * @param {Object} config Configuration object for the EduSharing component
	 */
	const initIsolatedElement = ( wrapper, config ) => {
		const frame = document.createElement( 'iframe' );
		frame.className = 'edusharing-render-frame';
		frame.title = 'EduSharing';
		frame.setAttribute( 'scrolling', 'no' );
		frame.style.border = '0';
		frame.style.display = 'block';
		frame.style.maxWidth = '100%';
		frame.style.width = config.width ? config.width + 'px' : '100%';
		frame.srcdoc = '<!doctype html><html><head><meta charset="utf-8">' +
			'<meta name="viewport" content="width=device-width,initial-scale=1">' +
			'<style>html,body{margin:0;padding:0;overflow:hidden}</style>' +
			'</head><body></body></html>';

		frame.addEventListener( 'load', () => {
			const frameWindow = frame.contentWindow;
			const frameDocument = frame.contentDocument;
			if ( !frameWindow || !frameDocument ) {
				return;
			}

			installRewrite( config.apiUrl, config.assetsBaseUrl, frameWindow );
			loadAssetsOnce( config, frameWindow );

			const resize = () => {
				const height = Math.max(
					frameDocument.documentElement.scrollHeight,
					frameDocument.body.scrollHeight
				);
				if ( height ) {
					frame.style.height = Math.ceil( height ) + 'px';
				}
			};

			const waitForElement = () => {
				if ( frameWindow.customElements.get( 'edu-sharing-render' ) ) {
					initElement( frameDocument.body, config, frameWindow );
					const observer = new frameWindow.ResizeObserver( resize );
					observer.observe( frameDocument.body );
					resize();
					return;
				}
				frameWindow.setTimeout( waitForElement, 50 );
			};
			waitForElement();
		}, { once: true } );

		wrapper.innerHTML = '';
		wrapper.appendChild( frame );
	};

	/**
	 * Initializes EduSharing rendering for all matching elements.
	 *
	 * @method init
	 * @param {HTMLElement} [root=document] The root element to search for EduSharing components
	 */
	const init = ( root ) => {
		Array.prototype.forEach.call(
			( root || document ).querySelectorAll( '.edusharing-render[data-edusharing-config]' ),
			( wrapper ) => {
				if ( wrapper.dataset.edusharingInit === '1' ) {
					return;
				}
				const rawConfig = wrapper.getAttribute( 'data-edusharing-config' );
				if ( !rawConfig ) {
					return;
				}
				let config;
				try {
					config = JSON.parse( rawConfig );
				} catch ( e ) {
					return;
				}
				wrapper.dataset.edusharingInit = '1';
				const isolateRenderer = directRendererAssigned;
				directRendererAssigned = true;
				loadAssetsOnce( config, window );
				installRewrite( config.apiUrl, config.assetsBaseUrl, window );
				/**
				 * Waits for the custom element to be defined and initializes the EduSharing component.
				 *
				 * @method waitForElement
				 */
				const waitForElement = () => {
					if ( isolateRenderer ) {
						initIsolatedElement( wrapper, config );
						return;
					}
					if ( window.customElements && window.customElements.get( 'edu-sharing-render' ) ) {
						initElement( wrapper, config, window );
						return;
					}
					setTimeout( waitForElement, 50 );
				};
				registerServiceWorker( config ).then( waitForElement );
			}
		);
	};

	// Run on initial page load
	if ( document.readyState === 'complete' || document.readyState === 'interactive' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', () => init() );
	}

	// Re-run after post-edit reloads and other content replacements
	if ( typeof mw !== 'undefined' && mw.hook ) {
		mw.hook( 'wikipage.content' ).add( ( $content ) => {
			const node = $content && $content[ 0 ] ? $content[ 0 ] : document;
			init( node );
		} );
		mw.hook( 've.deactivationComplete' ).add( () => {
			// Frames lose their document while VisualEditor detaches the parser
			// output. Recreate them after the original page content is restored.
			setTimeout( () => {
				document.querySelectorAll( '.edusharing-render-frame' ).forEach( ( frame ) => {
					const wrapper = frame.closest( '.edusharing-render[data-edusharing-config]' );
					if ( wrapper ) {
						delete wrapper.dataset.edusharingInit;
					}
				} );
				init( document );
			}, 0 );
		} );
		mw.hook( 'postEdit' ).add( () => {
			// The rendering web component does not start a second rendering job after
			// VisualEditor replaces page content in the same document. Load the fresh
			// view once after saving instead of leaving an incomplete component behind.
			if ( document.querySelector( '.ve-ce-surface' ) &&
				document.querySelector( '.edusharing-render[data-edusharing-config]' ) ) {
				window.location.replace( mw.util.getUrl( mw.config.get( 'wgPageName' ) ) );
			}
		} );
	}
}() );
