/**
 * Admin JavaScript interactions for SVGFlow.
 *
 * @package SVGFlow
 * @since   1.0.0
 */

document.addEventListener( 'DOMContentLoaded', function() {
	var copyButtons = document.querySelectorAll( '.svg-flow-copy-btn' );

	copyButtons.forEach( function( btn ) {
		btn.addEventListener( 'click', function() {
			var targetId = this.getAttribute( 'data-target' );
			var targetEl = document.getElementById( targetId );

			if ( ! targetEl ) {
				return;
			}

			var textToCopy = targetEl.textContent || targetEl.innerText;

			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( textToCopy.trim() ).then( function() {
					showCopiedState( btn );
				} ).catch( function() {
					fallbackCopy( textToCopy, btn );
				} );
			} else {
				fallbackCopy( textToCopy, btn );
			}
		} );
	} );

	function showCopiedState( btn ) {
		var originalText = btn.innerHTML;
		btn.classList.add( 'copied' );
		btn.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied!';

		setTimeout( function() {
			btn.classList.remove( 'copied' );
			btn.innerHTML = originalText;
		}, 2000 );
	}

	function fallbackCopy( text, btn ) {
		var tempInput = document.createElement( 'input' );
		tempInput.value = text.trim();
		document.body.appendChild( tempInput );
		tempInput.select();
		try {
			document.execCommand( 'copy' );
			showCopiedState( btn );
		} catch ( err ) {
			// Fallback silently if clipboard unavailable.
		}
		document.body.removeChild( tempInput );
	}
} );
