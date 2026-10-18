/**
 * WooToolkit variation swatches.
 *
 * Each swatch group sits next to WooCommerce's own <select>. Choosing a swatch sets the select's value
 * and triggers "change", so WooCommerce's variation script (wc-add-to-cart-variation) stays in charge of
 * prices, images and stock. After WooCommerce updates the selects (woocommerce_update_variation_values),
 * the swatches copy their state: options it removed or disabled are greyed out, out-of-stock ones marked.
 */
( function ( $ ) {
	'use strict';

	if ( ! $ ) {
		return;
	}

	var i18n = window.wootoolkitSwatches || {};
	var decoder = document.createElement( 'textarea' );

	document.documentElement.classList.add( 'wootoolkit-swatches-js' );

	function decode( value ) {
		decoder.innerHTML = String( value );
		return decoder.value;
	}

	function format( template, value ) {
		return template ? String( template ).replace( '%s', value ) : value;
	}

	function selectOf( group ) {
		return $( group ).closest( '.wootoolkit-swatches-wrap' ).children( 'select' ).first();
	}

	function attributeName( $select ) {
		return $select.data( 'attribute_name' ) || $select.attr( 'name' );
	}

	function formOf( $el ) {
		return $el.closest( 'form.variations_form' );
	}

	/**
	 * For each value of the select's attribute, whether a variation that matches the other choices is
	 * in stock. Null when the variations are not in the page (WooCommerce loads them by AJAX).
	 */
	function stockMap( $select ) {
		var $form = formOf( $select );
		var variations = $form.data( 'product_variations' );
		if ( ! Array.isArray( variations ) ) {
			return null;
		}
		var name = attributeName( $select );
		var chosen = {};
		$form.find( '.variations select' ).each( function () {
			var $other = $( this );
			chosen[ attributeName( $other ) ] = $other.val() || '';
		} );
		var all = $select.closest( '.wootoolkit-swatches-wrap' ).find( '.wootoolkit-swatch' ).map( function () {
			return this.getAttribute( 'data-value' );
		} ).get();
		var map = {};
		variations.forEach( function ( variation ) {
			var attrs = variation.attributes || {};
			for ( var key in attrs ) {
				if ( key === name || ! Object.prototype.hasOwnProperty.call( attrs, key ) ) {
					continue;
				}
				var want = chosen[ key ] || '';
				var have = attrs[ key ] ? decode( attrs[ key ] ) : '';
				if ( want && have && want !== have ) {
					return;
				}
			}
			var own = attrs[ name ] ? decode( attrs[ name ] ) : '';
			( own ? [ own ] : all ).forEach( function ( value ) {
				map[ value ] = !! map[ value ] || !! variation.is_in_stock;
			} );
		} );
		return map;
	}

	/**
	 * Copies the select's state to its swatches.
	 */
	function sync( group ) {
		var $group = $( group );
		var $select = selectOf( group );
		if ( ! $select.length ) {
			return;
		}
		var value = $select.val() || '';
		var options = {};
		$select.find( 'option' ).each( function () {
			if ( this.value ) {
				options[ this.value ] = ! this.disabled;
			}
		} );
		var stock = stockMap( $select );
		var hideOutOfStock = $group.hasClass( 'wootoolkit-swatches--oos-hide' );
		var $focusable = null;

		$group.find( '.wootoolkit-swatch' ).each( function () {
			var $swatch = $( this );
			var v = this.getAttribute( 'data-value' );
			var label = this.getAttribute( 'data-label' ) || v;
			var available = options[ v ] === true;
			var outOfStock = stock ? stock[ v ] === false : $swatch.hasClass( 'is-out-of-stock' );
			var selected = v === value;

			$swatch
				.toggleClass( 'is-selected', selected )
				.toggleClass( 'is-unavailable', ! available && ! outOfStock )
				.toggleClass( 'is-out-of-stock', outOfStock )
				.attr( 'aria-checked', selected ? 'true' : 'false' )
				.attr(
					'aria-label',
					outOfStock ? format( i18n.outOfStock, label ) : ( available ? label : format( i18n.unavailable, label ) )
				)
				.prop( 'hidden', hideOutOfStock && outOfStock && ! selected );

			if ( selected ) {
				$focusable = $swatch;
			}
		} );

		var $buttons = $group.find( '.wootoolkit-swatch' ).not( '[hidden]' );
		if ( ! $focusable ) {
			$focusable = $buttons.first();
		}
		$group.find( '.wootoolkit-swatch' ).attr( 'tabindex', '-1' );
		$focusable.attr( 'tabindex', '0' );
	}

	function syncForm( form ) {
		$( form ).find( '.wootoolkit-swatches' ).each( function () {
			sync( this );
		} );
	}

	/**
	 * Chooses a value. When WooCommerce has removed it because of the other choices, those are cleared
	 * first so it becomes available again.
	 */
	function choose( group, value ) {
		var $select = selectOf( group );
		if ( ! $select.length || ( $select.val() || '' ) === value ) {
			return;
		}
		var $form = formOf( $select );
		var available = $select.find( 'option' ).filter( function () {
			return this.value === value && ! this.disabled;
		} ).length > 0;

		if ( ! available && $form.length ) {
			$form.find( '.variations select' ).not( $select ).val( '' );
			$select.val( '' );
			$form.trigger( 'check_variations' );
		}
		$select.val( value ).trigger( 'change' );
		syncForm( $form.length ? $form : $select.closest( '.wootoolkit-swatches-wrap' ) );
	}

	function prepare( context ) {
		$( context ).find( '.wootoolkit-swatches-wrap' ).each( function () {
			// The swatches replace the select for keyboard and screen reader users too.
			$( this ).children( 'select' ).attr( { tabindex: '-1', 'aria-hidden': 'true' } );
			$( this ).find( '.wootoolkit-swatches' ).each( function () {
				sync( this );
			} );
		} );
	}

	$( document )
		.on( 'click', '.wootoolkit-swatches-wrap .wootoolkit-swatch', function ( event ) {
			event.preventDefault();
			choose( $( this ).closest( '.wootoolkit-swatches' ), this.getAttribute( 'data-value' ) );
			this.focus();
		} )
		.on( 'keydown', '.wootoolkit-swatches .wootoolkit-swatch', function ( event ) {
			var keys = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1, Home: 'first', End: 'last' };
			if ( ! Object.prototype.hasOwnProperty.call( keys, event.key ) ) {
				return;
			}
			event.preventDefault();
			// Arrow keys move the focus; Space or Enter chooses (so browsing never clears other choices).
			var $buttons = $( this ).closest( '.wootoolkit-swatches' ).find( '.wootoolkit-swatch' ).not( '[hidden]' );
			var index = $buttons.index( this );
			var step = keys[ event.key ];
			var next;
			if ( 'first' === step ) {
				next = 0;
			} else if ( 'last' === step ) {
				next = $buttons.length - 1;
			} else {
				next = ( index + step + $buttons.length ) % $buttons.length;
			}
			$buttons.attr( 'tabindex', '-1' );
			$buttons.eq( next ).attr( 'tabindex', '0' ).trigger( 'focus' );
		} )
		.on( 'change', '.wootoolkit-swatches-wrap > select', function () {
			$( this ).siblings( '.wootoolkit-swatches' ).each( function () {
				sync( this );
			} );
		} )
		.on( 'woocommerce_update_variation_values reset_data found_variation', 'form.variations_form', function () {
			syncForm( this );
		} )
		.on( 'wc_variation_form', 'form.variations_form', function () {
			prepare( this );
		} )
		.on( 'reset_focus', 'form.variations_form', function () {
			// WooCommerce focuses the first (hidden) select after "Clear": focus its swatches instead.
			var $first = $( this ).find( '.wootoolkit-swatches' ).first().find( '.wootoolkit-swatch[tabindex="0"]' );
			if ( $first.length ) {
				$first.trigger( 'focus' );
			}
		} );

	$( function () {
		prepare( document );
	} );

	// Lets other scripts (quick views, the Pro kit) prepare swatches they add later.
	window.wootoolkitSwatchesInit = prepare;
}( window.jQuery ) );
