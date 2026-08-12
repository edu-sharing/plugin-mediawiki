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

	/**
	 * Flag to track if URL rewrites are already installed.
	 *
	 * @type {boolean}
	 */
	let rewritesInstalled = false;
	let serviceWorkerPromise = null;

	/**
	 * Loads EduSharing rendering assets (script and stylesheet) only once.
	 *
	 * @method loadAssetsOnce
	 * @param {Object} config Configuration object containing script and style URLs
	 */
	const loadAssetsOnce = ( config ) => {
		if ( !document.querySelector( 'script[data-edusharing-rendering]' ) ) {
			const script = document.createElement( 'script' );
			script.type = 'module';
			script.src = config.scriptUrl;
			script.dataset.edusharingRendering = '1';
			document.head.appendChild( script );
		}
		if ( !document.querySelector( 'link[data-edusharing-rendering]' ) ) {
			const link = document.createElement( 'link' );
			link.rel = 'stylesheet';
			link.href = config.styleUrl;
			link.dataset.edusharingRendering = '1';
			document.head.appendChild( link );
		}
	};

	/**
	 * Installs URL rewrites for EduSharing API calls.
	 *
	 * @method installRewrite
	 * @param {string} apiUrl The base API URL for EduSharing
	 * @param {string} renderUrl The base rendering URL for EduSharing
	 */
	const installRewrite = ( apiUrl, assetsBaseUrl ) => {
		if ( rewritesInstalled ) {
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
				const parsedUrl = new URL( url, window.location.href );
				if ( parsedUrl.pathname.startsWith( '/edu-sharing/rest' ) ) {
					return apiUrl + parsedUrl.pathname.replace( '/edu-sharing/rest', '' ) + parsedUrl.search;
				}
				return url;
			} catch ( e ) {
				return url;
			}
		};

		// Override fetch to rewrite URLs
		const originalFetch = window.fetch.bind( window );
		window.fetch = ( ...args ) => {
			if ( args.length ) {
				args[ 0 ] = rewrite( args[ 0 ] );
			}
			return originalFetch( ...args );
		};

		// Override XMLHttpRequest to rewrite URLs
		const originalOpen = XMLHttpRequest.prototype.open;
		XMLHttpRequest.prototype.open = function ( method, url, ...rest ) {
			url = rewrite( url );
			return originalOpen.call( this, method, url, ...rest );
		};

		// Set global environment variables for EduSharing
		window.__env = window.__env || {};
		window.__env.EDU_SHARING_API_URL = apiUrl;
		window.__env.EDU_SHARING_BASE_URL = apiUrl;
		window.__env.EDU_SHARING_REST_URL = apiUrl;
		window.__env.API_BASE_URL = apiUrl;
		window.EDU_SHARING_API_URL = apiUrl;
		window.EDU_SHARING_BASE_URL = apiUrl;
		window.EDU_SHARING_REST_URL = apiUrl;
		window.__EDUSHARING_PUBLIC_PATH__ = assetsBaseUrl;

		rewritesInstalled = true;
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
	 */
	const initElement = ( wrapper, config ) => {
		if ( !wrapper ) {
			return;
		}
		wrapper.innerHTML = '';
		const element = document.createElement( 'edu-sharing-render' );
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
				loadAssetsOnce( config );
				installRewrite( config.apiUrl, config.assetsBaseUrl );
				/**
				 * Waits for the custom element to be defined and initializes the EduSharing component.
				 *
				 * @method waitForElement
				 */
				const waitForElement = () => {
					if ( window.customElements && window.customElements.get( 'edu-sharing-render' ) ) {
						initElement( wrapper, config );
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
	if ( window.mw && window.mw.hook ) {
		window.mw.hook( 'wikipage.content' ).add( ( $content ) => {
			const node = $content && $content[ 0 ] ? $content[ 0 ] : document;
			init( node );
		} );
		window.mw.hook( 'postEdit' ).add( () => {
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
