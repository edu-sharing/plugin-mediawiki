/* global mw */
( function () {
	'use strict';

	let rewritesInstalled = false;
	const loadAssetsOnce = ( config ) => {
		if ( !document.querySelector( 'script[data-edusharing-rendering]' ) ) {
			const s = document.createElement( 'script' );
			s.type = 'module';
			s.src = config.scriptUrl;
			s.dataset.edusharingRendering = '1';
			document.head.appendChild( s );
		}
		if ( !document.querySelector( 'link[data-edusharing-rendering]' ) ) {
			const l = document.createElement( 'link' );
			l.rel = 'stylesheet';
			l.href = config.styleUrl;
			l.dataset.edusharingRendering = '1';
			document.head.appendChild( l );
		}
	};

	const installRewrite = ( apiUrl, renderUrl ) => {
		if ( rewritesInstalled ) {
			return;
		}
		const rewrite = ( url ) => {
			try {
				const u = new URL( url, window.location.href );
				if ( u.pathname.startsWith( '/edu-sharing/rest' ) ) {
					return apiUrl + u.pathname.replace( '/edu-sharing/rest', '' ) + u.search;
				}
				return url;
			} catch ( e ) {
				return url;
			}
		};
		const origFetch = window.fetch.bind( window );
		window.fetch = ( ...args ) => {
			if ( args.length ) {
				args[ 0 ] = rewrite( args[ 0 ] );
			}
			return origFetch( ...args );
		};
		const origOpen = XMLHttpRequest.prototype.open;
		XMLHttpRequest.prototype.open = function ( method, url, ...rest ) {
			url = rewrite( url );
			return origOpen.call( this, method, url, ...rest );
		};
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

	const registerServiceWorker = async ( config ) => {
		if ( !config.activateServiceWorker || !config.serviceWorkerUrl || !('serviceWorker' in navigator ) ) {
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
			console.warn( 'edu-sharing service worker registration failed', e ); // eslint-disable-line no-console
		}
	};

	const initElement = ( wrapper, config ) => {
		if ( !wrapper ) {
			return;
		}
		wrapper.innerHTML = '';
		const el = document.createElement( 'edu-sharing-render' );
		el.encoded_node = config.encodedNode;
		el.signature = config.signature;
		el.jwt = config.jwt;
		el.render_url = config.renderUrl;
		el.encoded_user = config.encodedUser;
		el.service_worker_url = config.serviceWorkerUrl;
		el.activate_service_worker = !!config.activateServiceWorker;
		el.assets_url = config.assetsUrl;
		el.preview_url = config.previewUrl;
		el.resource_url = config.resourceUrl;
		el.style.maxWidth = '100%';
		if ( config.width ) {
			el.style.width = config.width + 'px';
		}
		wrapper.appendChild( el );

		// Enforce link target for resource links if configured
		if ( config.openInNewTab ) {
			const setTargets = () => {
				const root = el.shadowRoot || el;
				root.querySelectorAll( 'a' ).forEach( ( a ) => {
					a.target = '_blank';
				} );
			};
			let tries = 0;
			const poll = setInterval( () => {
				setTargets();
				tries++;
				if ( tries > 200 ) {
					clearInterval( poll );
				}
			}, 50 );
			const obs = new MutationObserver( () => setTargets() );
			obs.observe( el.shadowRoot || el, { childList: true, subtree: true } );
			setTimeout( () => obs.disconnect(), 10000 );
		}
	};

	const init = ( root ) => {
		( root || document ).querySelectorAll( '.edusharing-render[data-edusharing-config]' ).forEach( ( wrapper ) => {
			if ( wrapper.dataset.edusharingInit === '1' ) {
				return;
			}
			const raw = wrapper.getAttribute( 'data-edusharing-config' );
			if ( !raw ) {
				return;
			}
			let config;
			try {
				config = JSON.parse( raw );
			} catch ( e ) {
				return;
			}
			wrapper.dataset.edusharingInit = '1';
			loadAssetsOnce( config );
			installRewrite( config.apiUrl, config.renderUrl );
			registerServiceWorker( config ).catch( () => {} );
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
	if ( mw && mw.hook ) {
		mw.hook( 'wikipage.content' ).add( ( $content ) => {
			// $content is a jQuery object; pass underlying node if present
			const node = $content && $content[ 0 ] ? $content[ 0 ] : document;
			init( node );
		} );
		mw.hook( 'postEdit' ).add( () => init() );
	}
}() );
