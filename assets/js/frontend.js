/**
 * High-performance Vanilla JS SVG Inliner for SVGFlow.
 *
 * Features:
 * - Zero external dependencies (< 2KB)
 * - In-memory request deduplication & caching
 * - MutationObserver for AJAX, Gutenberg, and page builders
 * - Full attribute, CSS class, and accessibility preservation
 *
 * @package SVGFlow
 * @since   1.0.0
 */

( function() {
	'use strict';

	var config   = window.svgFlowConfig || {};
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

	/**
	 * Inline a single <img> element with its SVG content.
	 *
	 * @param {HTMLImageElement} img The image element to replace.
	 */
	function inlineSvg( img ) {
		// Prevent double processing.
		if ( img.dataset.svgFlowProcessing || img.classList.contains( 'svg-flow-inlined' ) ) {
			return;
		}

		var src = img.getAttribute( 'src' );
		if ( ! src || src.indexOf( '.svg' ) === -1 ) {
			return;
		}

		img.dataset.svgFlowProcessing = 'true';

		var fetchPromise;

		if ( svgCache.has( src ) ) {
			fetchPromise = Promise.resolve( svgCache.get( src ) );
		} else {
			fetchPromise = fetch( src )
				.then( function( response ) {
					if ( ! response.ok ) {
						throw new Error( 'Network response was not ok' );
					}
					return response.text();
				} )
				.then( function( rawSvg ) {
					svgCache.set( src, rawSvg );
					return rawSvg;
				} );
		}

		fetchPromise
			.then( function( svgText ) {
				var parser  = new DOMParser();
				var doc     = parser.parseFromString( svgText, 'image/svg+xml' );
				var svgElem = doc.querySelector( 'svg' );

				if ( ! svgElem ) {
					return;
				}

				// Copy ID if present.
				if ( img.id ) {
					svgElem.id = img.id;
				}

				// Copy and merge CSS classes.
				var existingClasses = ( img.getAttribute( 'class' ) || '' ).split( /\s+/ );
				existingClasses.forEach( function( cls ) {
					if ( cls && cls !== 'svg-flow-processing' ) {
						svgElem.classList.add( cls );
					}
				} );
				svgElem.classList.add( 'svg-flow-inlined' );

				// Copy inline styles.
				var imgStyle = img.getAttribute( 'style' );
				if ( imgStyle ) {
					var currentSvgStyle = svgElem.getAttribute( 'style' ) || '';
					svgElem.setAttribute( 'style', currentSvgStyle + ';' + imgStyle );
				}

				// Copy data attributes.
				Array.prototype.slice.call( img.attributes ).forEach( function( attr ) {
					if ( attr.name.indexOf( 'data-' ) === 0 ) {
						svgElem.setAttribute( attr.name, attr.value );
					}
				} );

				// Preserve Accessibility: role, aria-label, alt title.
				if ( ! svgElem.getAttribute( 'role' ) ) {
					svgElem.setAttribute( 'role', 'img' );
				}

				var alt = img.getAttribute( 'alt' );
				if ( alt && ! svgElem.querySelector( 'title' ) ) {
					var titleTag = document.createElementNS( 'http://www.w3.org/2000/svg', 'title' );
					titleTag.textContent = alt;
					svgElem.insertBefore( titleTag, svgElem.firstChild );
				}

				// Inherit class from parent container if parent was targeted.
				if ( img.parentElement && img.parentElement.classList ) {
					Array.prototype.slice.call( img.parentElement.classList ).forEach( function( parentCls ) {
						if ( parentCls === 'style-svg' || parentCls === 'svg-flow' ) {
							svgElem.classList.add( parentCls );
						}
					} );
				}

				// Replace image node with SVG.
				if ( img.parentNode ) {
					img.parentNode.replaceChild( svgElem, img );

					// Dispatch custom event for theme and third-party JS integration.
					var event;
					if ( typeof CustomEvent === 'function' ) {
						event = new CustomEvent( 'svgflow:inlined', {
							bubbles: true,
							detail: {
								svg: svgElem,
								src: src
							}
						} );
					} else {
						event = document.createEvent( 'CustomEvent' );
						event.initCustomEvent( 'svgflow:inlined', true, true, {
							svg: svgElem,
							src: src
						} );
					}
					svgElem.dispatchEvent( event );
				}
			} )
			.catch( function( err ) {
				// Remove processing lock so fallback image remains functional.
				delete img.dataset.svgFlowProcessing;
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
