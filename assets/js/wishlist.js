/**
 * Wishlist hearts and counters. Pages are printed without per-visitor state (so they can be cached);
 * this script asks the REST endpoint which products are on the visitor's wishlist and updates them.
 * No dependencies.
 */
( function () {
	'use strict';

	var cfg = window.wootoolkitWishlist;
	if ( ! cfg || ! window.fetch ) {
		return;
	}
	var state = { count: 0, items: [] };
	var nonce = cfg.nonce || '';
	var live;

	function hasGuestList() {
		return document.cookie.split( ';' ).some( function ( part ) {
			return part.trim().indexOf( cfg.flagCookie + '=' ) === 0;
		} );
	}

	function announce( text ) {
		if ( ! live ) {
			live = document.createElement( 'div' );
			live.className = 'screen-reader-text';
			live.setAttribute( 'aria-live', 'polite' );
			document.body.appendChild( live );
		}
		live.textContent = text;
	}

	function refreshNonce() {
		return fetch( cfg.ajaxUrl + '?action=rest-nonce', { credentials: 'same-origin' } )
			.then( function ( r ) {
				return r.ok ? r.text() : '0';
			} )
			.then( function ( text ) {
				nonce = text && text !== '0' && text.length < 20 ? text : '';
			} );
	}

	function request( method, path, body, retried ) {
		var headers = { Accept: 'application/json' };
		if ( nonce ) {
			headers[ 'X-WP-Nonce' ] = nonce;
		}
		if ( body ) {
			headers[ 'Content-Type' ] = 'application/json';
		}
		return fetch( cfg.rest + path, {
			method: method,
			credentials: 'same-origin',
			headers: headers,
			body: body ? JSON.stringify( body ) : undefined,
		} ).then( function ( response ) {
			return response.json().then( function ( data ) {
				// A stale nonce (cached page, new login): get a fresh one and retry once.
				if ( ! retried && response.status === 403 && data && data.code === 'rest_cookie_invalid_nonce' ) {
					return refreshNonce().then( function () {
						return request( method, path, body, true );
					} );
				}
				if ( ! response.ok ) {
					var error = new Error( ( data && data.message ) || cfg.i18n.error );
					error.data = data;
					error.status = response.status;
					throw error;
				}
				return data;
			} );
		} );
	}

	function contains( productId ) {
		return state.items.some( function ( item ) {
			return String( item.product_id ) === String( productId );
		} );
	}

	function apply( next ) {
		state = next && next.items ? next : { count: 0, items: [] };
		document.querySelectorAll( '.wootoolkit-wishlist-button' ).forEach( function ( button ) {
			var on = contains( button.getAttribute( 'data-product-id' ) );
			var label = button.querySelector( '.wootoolkit-wishlist-label' );
			button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			button.classList.toggle( 'is-added', on );
			if ( label ) {
				label.textContent = button.getAttribute( on ? 'data-added-text' : 'data-add-text' );
			}
		} );
		document.querySelectorAll( '[data-wootoolkit-wishlist-count]' ).forEach( function ( counter ) {
			counter.textContent = state.count > 0 ? String( state.count ) : '';
		} );
		document.dispatchEvent( new CustomEvent( 'wootoolkit-wishlist-updated', { detail: state } ) );
	}

	function selectedVariation( productId ) {
		var input = document.querySelector( 'form.variations_form[data-product_id="' + productId + '"] input[name="variation_id"]' );
		return input && parseInt( input.value, 10 ) > 0 ? parseInt( input.value, 10 ) : 0;
	}

	function toggle( button ) {
		var productId = parseInt( button.getAttribute( 'data-product-id' ), 10 );
		var removing = button.getAttribute( 'aria-pressed' ) === 'true';
		var call = removing
			? request( 'DELETE', '/items/' + productId )
			: request( 'POST', '/items', { product_id: productId, variation_id: selectedVariation( productId ) } );
		button.classList.add( 'is-loading' );
		button.classList.remove( 'is-error' );
		button.removeAttribute( 'title' );
		button.setAttribute( 'aria-busy', 'true' );
		call.then( function ( data ) {
			apply( data );
			announce( removing ? cfg.i18n.removed : cfg.i18n.added );
		} )
			.catch( function ( error ) {
				if ( error.status === 401 ) {
					window.location.href = ( error.data && error.data.data && error.data.data.login_url ) || cfg.loginUrl;
					return;
				}
				announce( error.message );
				button.classList.add( 'is-error' );
				button.setAttribute( 'title', error.message );
			} )
			.then( function () {
				button.classList.remove( 'is-loading' );
				button.removeAttribute( 'aria-busy' );
			} );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest && event.target.closest( '.wootoolkit-wishlist-button' );
		if ( button ) {
			event.preventDefault();
			toggle( button );
			return;
		}
		var copy = event.target.closest && event.target.closest( '.wootoolkit-wishlist-copy' );
		if ( copy ) {
			var field = document.getElementById( copy.getAttribute( 'data-target' ) );
			if ( field ) {
				field.select();
				if ( navigator.clipboard ) {
					navigator.clipboard.writeText( field.value );
				} else {
					document.execCommand( 'copy' );
				}
				announce( cfg.i18n.copied );
			}
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		var button = event.target.closest && event.target.closest( '.wootoolkit-wishlist-button' );
		if ( button && ( event.key === ' ' || event.key === 'Spacebar' ) ) {
			event.preventDefault();
			toggle( button );
		}
	} );

	function init() {
		if ( ! document.querySelector( '.wootoolkit-wishlist-button, [data-wootoolkit-wishlist-count]' ) ) {
			return;
		}
		// Guests without a list: nothing to fetch, and no request for most visitors.
		if ( ! cfg.loggedIn && ! hasGuestList() ) {
			apply( null );
			return;
		}
		request( 'GET', '' ).then( apply ).catch( function () {} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	window.wootoolkitWishlistApi = { request: request, apply: apply, state: function () { return state; } };
} )();
