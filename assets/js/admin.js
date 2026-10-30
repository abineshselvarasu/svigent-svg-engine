/**
 * Settings screen copy-to-clipboard button.
 *
 * @package SvigentTools
 * @since   1.0.0
 */

document.addEventListener( 'DOMContentLoaded', function() {
	document.querySelectorAll( '.svg-flow-copy-btn' ).forEach( function( btn ) {
		btn.addEventListener( 'click', function() {
			var targetEl = document.getElementById( btn.getAttribute( 'data-target' ) );

			if ( ! targetEl ) {
				return;
			}

			var text = targetEl.textContent.trim();

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( function() {
					showCopiedState( btn );
				} ).catch( function() {
					fallbackCopy( text, btn );
				} );
			} else {
				fallbackCopy( text, btn );
			}
		} );
	} );

	function showCopiedState( btn ) {
		var original = Array.prototype.slice.call( btn.childNodes );
		var icon     = document.createElement( 'span' );

		icon.className = 'dashicons dashicons-yes';
		btn.classList.add( 'copied' );
		btn.replaceChildren( icon, document.createTextNode( ' ' + ( btn.getAttribute( 'data-copied-label' ) || 'Copied!' ) ) );

		setTimeout( function() {
			btn.classList.remove( 'copied' );
			btn.replaceChildren.apply( btn, original );
		}, 2000 );
	}

	function fallbackCopy( text, btn ) {
		var tempInput = document.createElement( 'input' );

		tempInput.value = text;
		document.body.appendChild( tempInput );
		tempInput.select();

		try {
			document.execCommand( 'copy' );
			showCopiedState( btn );
		} catch ( err ) {
			// Clipboard unavailable; nothing else to do.
		}

		document.body.removeChild( tempInput );
	}
} );
