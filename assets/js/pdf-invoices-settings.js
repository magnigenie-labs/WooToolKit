/**
 * PDF invoices settings: a "Choose logo" button next to the logo field (WordPress media library).
 */
( function ( $ ) {
	'use strict';
	$( function () {
		var $field = $( 'input.wootoolkit-pdf-logo' );
		if ( ! $field.length || ! window.wp || ! wp.media ) {
			return;
		}
		var l10n = window.wootoolkitPdfInvoices || {};
		var frame;
		var $button = $( '<button type="button" class="button" style="margin-left:6px"></button>' ).text( l10n.choose || 'Choose logo' );
		$field.after( $button );
		$button.on( 'click', function ( event ) {
			event.preventDefault();
			if ( ! frame ) {
				frame = wp.media( {
					title: l10n.choose || 'Choose logo',
					button: { text: l10n.use || 'Use this image' },
					library: { type: [ 'image/jpeg', 'image/png' ] },
					multiple: false
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first();
					if ( attachment ) {
						$field.val( String( attachment.get( 'id' ) ) ).trigger( 'change' );
					}
				} );
			}
			frame.open();
		} );
	} );
}( jQuery ) );
