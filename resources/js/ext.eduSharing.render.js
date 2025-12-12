/**
 * EduSharing Rendering Service Integration for MediaWiki 1.43.
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
	* @type {boolean}
	*/
   let rewritesInstalled = false;

   /**
	* Loads EduSharing rendering assets (script and stylesheet) only once.
	*
	* @function loadAssetsOnce
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
	* @function installRewrite
	* @param {string} apiUrl The base API URL for EduSharing
	* @param {string} renderUrl The base rendering URL for EduSharing
	*/
   const installRewrite = ( apiUrl, renderUrl ) => {
	   if ( rewritesInstalled ) {
		   return;
	   }

	   /**
		* Rewrites URLs to use the configured EduSharing API URL.
		*
		* @function rewrite
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
	   window.__EDUSHARING_PUBLIC_PATH__ = renderUrl + '/web-components/rendering-service/';

	   rewritesInstalled = true;
   };

   /**
	* Registers the EduSharing service worker if enabled.
	*
	* @async
	* @function registerServiceWorker
	* @param {Object} config Configuration object containing service worker settings
	*/
   const registerServiceWorker = async ( config ) => {
	   if ( !config.activateServiceWorker || !config.serviceWorkerUrl || !('serviceWorker' in navigator) ) {
		   return;
	   }
	   if ( window.__eduSharingSW ) {
		   return;
	   }
	   window.__eduSharingSW = true;
	   try {
		   await navigator.serviceWorker.register( config.serviceWorkerUrl, { scope: '/' } );
		   await navigator.serviceWorker.ready;
	   } catch ( e ) {
		   console.warn( 'EduSharing service worker registration failed', e );
	   }
   };

   /**
	* Initializes the EduSharing rendering element.
	*
	* @function initElement
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
	   element.activate_service_worker = !!config.activateServiceWorker;
	   element.assets_url = config.assetsUrl;
	   element.preview_url = config.previewUrl;
	   element.resource_url = config.resourceUrl;
	   element.style.maxWidth = '100%';
	   if ( config.width ) {
		   element.style.width = config.width + 'px';
	   }
	   wrapper.appendChild( element );

	   // Enforce link target for resource links if configured
	   if ( config.openInNewTab ) {
		   /**
			* Sets the target attribute for all links in the EduSharing component.
			*
			* @function setTargets
			*/
		   const setTargets = () => {
			   const root = element.shadowRoot || element;
			   root.querySelectorAll( 'a' ).forEach( ( link ) => {
				   link.target = '_blank';
			   } );
		   };
		   let attempts = 0;
		   const poll = setInterval( () => {
			   setTargets();
			   attempts++;
			   if ( attempts > 200 ) {
				   clearInterval( poll );
			   }
		   }, 50 );
		   const observer = new MutationObserver( () => setTargets() );
		   observer.observe( element.shadowRoot || element, { childList: true, subtree: true } );
		   setTimeout( () => observer.disconnect(), 10000 );
	   }
   };

   /**
	* Initializes EduSharing rendering for all matching elements.
	*
	* @function init
	* @param {HTMLElement} [root=document] The root element to search for EduSharing components
	*/
   const init = ( root ) => {
	   ( root || document ).querySelectorAll( '.edusharing-render[data-edusharing-config]' ).forEach( ( wrapper ) => {
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
		   installRewrite( config.apiUrl, config.renderUrl );
		   registerServiceWorker( config ).catch( () => {} );

		   /**
			* Waits for the custom element to be defined and initializes the EduSharing component.
			*
			* @function waitForElement
			*/
		   const waitForElement = () => {
			   if ( window.customElements && window.customElements.get( 'edu-sharing-render' ) ) {
				   initElement( wrapper, config );
				   return;
			   }
			   setTimeout( waitForElement, 50 );
		   };
		   waitForElement();
	   } );
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
	   window.mw.hook( 'postEdit' ).add( () => init() );
   }
}() );
