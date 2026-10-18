/**
 * WooToolkit variation swatches: colour picker and image picker on attribute term screens.
 */
( function ( $ ) {
	'use strict';

	var i18n = window.wootoolkitSwatchesAdmin || {};

	function initColor( context ) {
		$( context ).find( 'input.wootoolkit-swatch-color' ).each( function () {
			if ( ! $( this ).closest( '.wp-picker-container' ).length && $.fn.wpColorPicker ) {
				$( this ).wpColorPicker();
			}
		} );
	}

	function setImage( $box, id, url ) {
		$box.find( 'input[type="hidden"]' ).val( id || '' );
		$box.find( '.wootoolkit-swatch-image__preview' ).html(
			url ? $( '<img />', { src: url, alt: '', style: 'max-width:60px;height:auto' } ) : ''
		);
		$box.find( '.wootoolkit-swatch-image__remove' ).prop( 'hidden', ! id );
	}

	$( document ).on( 'click', '.wootoolkit-swatch-image__choose', function ( event ) {
		event.preventDefault();
		var $box = $( this ).closest( '.wootoolkit-swatch-image' );
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		var frame = window.wp.media( {
			title: i18n.title,
			button: { text: i18n.button },
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', function () {
			var image = frame.state().get( 'selection' ).first().toJSON();
			var sizes = image.sizes || {};
			var url = sizes.thumbnail ? sizes.thumbnail.url : image.url;
			setImage( $box, image.id, url );
		} );
		frame.open();
	} );

	$( document ).on( 'click', '.wootoolkit-swatch-image__remove', function ( event ) {
		event.preventDefault();
		setImage( $( this ).closest( '.wootoolkit-swatch-image' ), '', '' );
	} );

	// The "Add new term" form is submitted by AJAX and cleared afterwards: clear the swatch fields too.
	$( document ).ajaxComplete( function ( event, xhr, settings ) {
		if ( ! settings || 'string' !== typeof settings.data || -1 === settings.data.indexOf( 'action=add-tag' ) ) {
			return;
		}
		var $form = $( '#addtag' );
		if ( ! $form.length || $form.find( '.form-invalid' ).length ) {
			return;
		}
		$form.find( '.wootoolkit-swatch-image' ).each( function () {
			setImage( $( this ), '', '' );
		} );
		$form.find( 'input.wootoolkit-swatch-color' ).each( function () {
			if ( $.fn.wpColorPicker && $( this ).closest( '.wp-picker-container' ).length ) {
				$( this ).wpColorPicker( 'color', '' );
			}
			$( this ).val( '' );
		} );
	} );

	$( function () {
		initColor( document );
	} );
}( window.jQuery ) );
