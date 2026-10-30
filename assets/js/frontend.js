/**
 * Inline SVG engine: swaps matching <img> tags for sanitized inline <svg> markup.
 *
 * Only SVGs from trusted locations are fetched, each URL is fetched once,
 * and a MutationObserver picks up images added later (AJAX, sliders, block editor).
 *
 * @package SvigentTools
 * @since   1.0.0
 */

( function() {
	'use strict';

	var config      = window.svgFlowConfig || {};
	var rawSelector = config.selector || 'style-svg, svg-flow';

	// Smart selector builder: supports 'style-svg', '.style-svg', 'img.style-svg', and parent containers like '<figure class="style-svg">'
	function getResolvedSelector( raw ) {
		var parts = raw.split( ',' );
		var resolved = [];

		parts.forEach( function( part ) {
			var p = part.trim();
			if ( ! p ) {
				return;
			}
			resolved.push( p );

			// If it's a class like .style-svg or style-svg, generate both img.class and .class img
			var cleanClass = p.replace( /^img\./, '' ).replace( /^\./, '' );
			if ( cleanClass && cleanClass.indexOf( ' ' ) === -1 && cleanClass.indexOf( '>' ) === -1 ) {
				var imgDirect = 'img.' + cleanClass;
				var containerInner = '.' + cleanClass + ' img';
				if ( resolved.indexOf( imgDirect ) === -1 ) {
					resolved.push( imgDirect );
				}
				if ( resolved.indexOf( containerInner ) === -1 ) {
					resolved.push( containerInner );
				}
			}
		} );

		return resolved.join( ', ' );
	}

	var selector = getResolvedSelector( rawSelector );
	var svgCache = new Map();
	var skipped  = new WeakMap();

	// Base URLs (host + path prefix) SVGs may be fetched from. Scheme is ignored so http/https mixes still match.
	var allowedBases = ( config.allowedUrls || [] ).map( function( base ) {
		try {
			var u = new URL( base, window.location.href );
			return u.host + u.pathname;
		} catch ( e ) {
			return null;
		}
	} ).filter( Boolean );

	// Elements that can execute script or embed foreign documents.
	var blockedTags = 'script, foreignObject, iframe, embed, object, handler, listener';
	var unsafeUrl   = /^\s*(javascript|vbscript|data):/i;
	var safeDataUrl = /^\s*data:image\/(png|gif|jpe?g|webp);/i;

	/**
	 * Resolve an image src to an absolute URL if it points at a trusted location.
	 *
	 * @param {string} src Raw src attribute.
	 * @return {string|null} Absolute URL, or null if not allowed.
	 */
	function resolveTrustedUrl( src ) {
		var url;
		try {
			url = new URL( src, window.location.href );
		} catch ( e ) {
			return null;
		}

		if ( url.protocol !== 'http:' && url.protocol !== 'https:' ) {
			return null;
		}

		if ( ! /\.svgz?$/i.test( url.pathname ) ) {
			return null;
		}

		var target = url.host + url.pathname;
		for ( var i = 0; i < allowedBases.length; i++ ) {
			if ( target.indexOf( allowedBases[ i ] ) === 0 ) {
				return url.href;
			}
		}

		return null;
	}

	/**
	 * Strip anything executable from a parsed SVG before it enters the page.
	 *
	 * Uploads are already sanitized server-side; this is defense in depth for
	 * files that reached the server some other way (FTP, older uploads, etc.).
	 *
	 * @param {SVGSVGElement} svgElem Root SVG element.
	 */
	function scrubSvg( svgElem ) {
		Array.prototype.slice.call( svgElem.querySelectorAll( blockedTags ) ).forEach( function( el ) {
			el.parentNode.removeChild( el );
		} );

		var nodes = [ svgElem ].concat( Array.prototype.slice.call( svgElem.querySelectorAll( '*' ) ) );
		nodes.forEach( function( el ) {
			var tag = el.localName.toLowerCase();

			// <animate>/<set> can rewrite href or event attributes at runtime.
			if ( tag === 'animate' || tag === 'set' || tag === 'animatemotion' || tag === 'animatetransform' ) {
				var target = ( el.getAttribute( 'attributeName' ) || '' ).toLowerCase();
				if ( target.indexOf( 'on' ) === 0 || target.indexOf( 'href' ) !== -1 ) {
					el.parentNode.removeChild( el );
					return;
				}
			}

			Array.prototype.slice.call( el.attributes ).forEach( function( attr ) {
				var name = attr.name.toLowerCase();
				if ( name.indexOf( 'on' ) === 0 ) {
					el.removeAttribute( attr.name );
				} else if ( ( name === 'href' || name === 'xlink:href' || name === 'src' || name === 'action' || name === 'formaction' ) &&
					unsafeUrl.test( attr.value ) && ! safeDataUrl.test( attr.value ) ) {
					el.removeAttribute( attr.name );
				}
			} );
		} );
	}

	/**
	 * Fetch an SVG once per URL. Failed requests stay cached so they are not retried on every DOM change.
	 *
	 * @param {string} src Absolute, trusted URL.
	 * @return {Promise<string>} SVG source text.
	 */
	function loadSvg( src ) {
		if ( ! svgCache.has( src ) ) {
			svgCache.set( src, fetch( src, { credentials: 'same-origin' } ).then( function( response ) {
				if ( ! response.ok ) {
					throw new Error( response.status );
				}
				return response.text();
			} ) );
		}

		return svgCache.get( src );
	}

	/**
	 * Inline a single <img> element with its SVG content.
	 *
	 * @param {HTMLImageElement} img The image element to replace.
	 */
	function inlineSvg( img ) {
		var rawSrc = img.getAttribute( 'src' );

		if ( ! rawSrc || img.dataset.svgFlowProcessing || skipped.get( img ) === rawSrc ) {
			return;
		}

		var src = rawSrc.indexOf( '.svg' ) !== -1 ? resolveTrustedUrl( rawSrc ) : null;
		if ( ! src ) {
			skipped.set( img, rawSrc );
			return;
		}

		img.dataset.svgFlowProcessing = 'true';

		loadSvg( src )
			.then( function( svgText ) {
				var doc     = new DOMParser().parseFromString( svgText, 'image/svg+xml' );
				var svgElem = doc.documentElement;

				if ( ! svgElem || svgElem.localName !== 'svg' || doc.querySelector( 'parsererror' ) ) {
					throw new Error( 'invalid' );
				}

				scrubSvg( svgElem );

				if ( img.id ) {
					svgElem.id = img.id;
				}

				( img.getAttribute( 'class' ) || '' ).split( /\s+/ ).forEach( function( cls ) {
					if ( cls ) {
						svgElem.classList.add( cls );
					}
				} );
				svgElem.classList.add( 'svg-flow-inlined' );

				var imgStyle = img.getAttribute( 'style' );
				if ( imgStyle ) {
					svgElem.setAttribute( 'style', ( svgElem.getAttribute( 'style' ) || '' ) + ';' + imgStyle );
				}

				Array.prototype.slice.call( img.attributes ).forEach( function( attr ) {
					if ( attr.name.indexOf( 'data-' ) === 0 && attr.name.indexOf( 'data-svg-flow' ) !== 0 ) {
						svgElem.setAttribute( attr.name, attr.value );
					}
				} );

				if ( ! svgElem.getAttribute( 'role' ) ) {
					svgElem.setAttribute( 'role', 'img' );
				}

				var alt = img.getAttribute( 'alt' );
				if ( alt && ! svgElem.querySelector( 'title' ) ) {
					var titleTag = document.createElementNS( 'http://www.w3.org/2000/svg', 'title' );
					titleTag.textContent = alt;
					svgElem.insertBefore( titleTag, svgElem.firstChild );
				}

				// Keep the trigger class when it was set on a wrapper rather than the image.
				if ( img.parentElement ) {
					[ 'style-svg', 'svg-flow' ].forEach( function( cls ) {
						if ( img.parentElement.classList.contains( cls ) ) {
							svgElem.classList.add( cls );
						}
					} );
				}

				if ( img.parentNode ) {
					img.parentNode.replaceChild( svgElem, img );
					svgElem.dispatchEvent( new CustomEvent( 'svgflow:inlined', {
						bubbles: true,
						detail: { svg: svgElem, src: src }
					} ) );
				}
			} )
			.catch( function() {
				// Leave the original <img> in place and do not retry this URL.
				delete img.dataset.svgFlowProcessing;
				skipped.set( img, rawSrc );
			} );
	}

	/**
	 * Find and process all matching images currently in the DOM.
	 */
	function processAll() {
		try {
			var images = document.querySelectorAll( selector );
			for ( var i = 0; i < images.length; i++ ) {
				inlineSvg( images[i] );
			}
		} catch ( e ) {
			// Catch invalid selector gracefully.
		}
	}

	/**
	 * Observe dynamic DOM changes (AJAX, Gutenberg, Page Builders, attribute changes).
	 */
	function setupObserver() {
		if ( ! window.MutationObserver ) {
			return;
		}

		var observerTimeout;

		var observer = new MutationObserver( function( mutations ) {
			var shouldCheck = false;

			for ( var i = 0; i < mutations.length; i++ ) {
				var m = mutations[i];
				if ( m.addedNodes && m.addedNodes.length > 0 ) {
					shouldCheck = true;
					break;
				}
				if ( m.type === 'attributes' && ( m.attributeName === 'class' || m.attributeName === 'src' ) ) {
					shouldCheck = true;
					break;
				}
			}

			if ( shouldCheck ) {
				clearTimeout( observerTimeout );
				observerTimeout = setTimeout( processAll, 60 );
			}
		} );

		observer.observe( document.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: [ 'class', 'src' ]
		} );
	}

	// Expose public API for manual triggers.
	window.svgFlow = {
		processAll: processAll,
		inlineSvg: inlineSvg,
		selector: selector
	};

	// Initialize on DOM ready and load events.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function() {
			processAll();
			setupObserver();
		} );
	} else {
		processAll();
		setupObserver();
	}

	// Fallback execution on full window load to catch late-injected block styles.
	window.addEventListener( 'load', processAll );

} )();
