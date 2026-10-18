/**
 * Checkout fields settings: add and remove extra field rows, show the choices box for dropdowns,
 * and confirm "Reset to defaults". Plain JS, no build step.
 */
( function () {
	'use strict';

	var SELECTOR = '[data-wtk-cf]';

	function toggleOptions( field ) {
		var select = field.querySelector( '[data-wtk-cf-type]' );
		var box = field.querySelector( '[data-wtk-cf-options]' );
		if ( ! select || ! box ) {
			return;
		}
		var option = select.options[ select.selectedIndex ];
		box.hidden = ! option || option.getAttribute( 'data-options' ) !== '1';
	}

	function init() {
		document.querySelectorAll( SELECTOR + ' .wtk-cf__field' ).forEach( toggleOptions );
	}

	document.addEventListener( 'click', function ( event ) {
		var add = event.target.closest( SELECTOR + ' [data-wtk-cf-add]' );
		var remove = event.target.closest( SELECTOR + ' [data-wtk-cf-remove]' );
		var reset = event.target.closest( SELECTOR + ' [data-wtk-cf-reset]' );
		if ( add ) {
			event.preventDefault();
			var wrap = add.closest( SELECTOR );
			var index = parseInt( wrap.getAttribute( 'data-next' ), 10 ) || 0;
			wrap.setAttribute( 'data-next', String( index + 1 ) );
			var table = wrap.querySelector( '.wtk-cf__table' );
			table.insertAdjacentHTML( 'beforeend', wrap.querySelector( 'template' ).innerHTML.replace( /__i__/g, String( index ) ) );
			var field = table.lastElementChild;
			var order = field.querySelector( 'input[type="number"]' );
			if ( order ) {
				order.value = String( ( parseInt( order.value, 10 ) || 0 ) + index );
			}
			toggleOptions( field );
			var label = field.querySelector( 'input[name$="[label]"]' );
			if ( label ) {
				label.focus();
			}
			if ( window.jQuery ) {
				window.jQuery( document.body ).trigger( 'wc-enhanced-select-init' );
			}
			table.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} else if ( remove ) {
			event.preventDefault();
			var row = remove.closest( '.wtk-cf__field' );
			var wrapper = row.closest( SELECTOR );
			row.remove();
			wrapper.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		} else if ( reset ) {
			// eslint-disable-next-line no-alert
			if ( ! window.confirm( reset.getAttribute( 'data-confirm' ) ) ) {
				event.preventDefault();
				return;
			}
			window.onbeforeunload = null;
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		if ( event.target.matches && event.target.matches( SELECTOR + ' [data-wtk-cf-type]' ) ) {
			toggleOptions( event.target.closest( '.wtk-cf__field' ) );
		}
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
