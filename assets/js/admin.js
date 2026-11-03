/**
 * WooToolkit admin pages: kit switches, filters, the Pro kit install flow, dialogs, tabs and copy.
 * Plain JavaScript, no build step. Settings come from the localized `wootoolkitAdmin` object.
 */
( function () {
	'use strict';

	var data = window.wootoolkitAdmin || {};
	var i18n = data.i18n || {};

	function t( key, fallback ) {
		return Object.prototype.hasOwnProperty.call( i18n, key ) ? i18n[ key ] : fallback;
	}

	function format( text ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var n = 0;
		return String( text ).replace( /%(?:(\d+)\$)?s/g, function ( match, index ) {
			var value = index ? args[ parseInt( index, 10 ) - 1 ] : args[ n++ ];
			return value === undefined ? '' : String( value );
		} );
	}

	function all( selector, root ) {
		return Array.prototype.slice.call( ( root || document ).querySelectorAll( selector ) );
	}

	function announce( text ) {
		var live = document.querySelector( '[data-wtk-live]' );
		if ( live ) {
			live.textContent = '';
			window.setTimeout( function () {
				live.textContent = text;
			}, 50 );
		}
	}

	function post( params ) {
		var body = new window.FormData();
		Object.keys( params ).forEach( function ( key ) {
			body.append( key, params[ key ] );
		} );
		return window.fetch( data.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } ).then( function ( response ) {
			return response.json().catch( function () {
				return { success: false, data: { message: t( 'failed', 'Something went wrong.' ) } };
			} );
		} );
	}

	/* Notices ------------------------------------------------------------------------------- */

	function notice( text, tone, linkUrl, linkText ) {
		var box = document.querySelector( '[data-wtk-notices]' );
		if ( ! box ) {
			announce( text );
			return;
		}
		var el = document.createElement( 'div' );
		el.className = 'wtk-notice wtk-notice--' + ( tone || 'success' );
		el.setAttribute( 'role', 'error' === tone ? 'alert' : 'status' );
		var strong = document.createElement( 'strong' );
		strong.className = 'wtk-notice__text';
		strong.textContent = text;
		el.appendChild( strong );
		if ( linkUrl ) {
			var link = document.createElement( 'a' );
			link.href = linkUrl;
			link.textContent = linkText;
			el.appendChild( link );
		}
		var close = document.createElement( 'button' );
		close.type = 'button';
		close.className = 'wtk-notice__dismiss';
		close.textContent = t( 'dismiss', 'Dismiss' );
		close.addEventListener( 'click', function () {
			el.remove();
		} );
		el.appendChild( close );
		box.innerHTML = '';
		box.appendChild( el );
	}

	// WooToolkit's own notices (class "wootoolkit-notice") move into the page, under the header.
	( function () {
		var page = document.querySelector( '.wtk-page' );
		if ( ! page ) {
			return;
		}
		all( '#wpbody-content > .wootoolkit-notice' ).forEach( function ( el ) {
			var target = page.querySelector( '[data-wtk-notices]' );
			if ( target ) {
				target.appendChild( el );
			} else {
				page.insertBefore( el, page.firstChild.nextSibling );
			}
		} );
	}() );

	/* Kit switches -------------------------------------------------------------------------- */

	function cardFor( id ) {
		return document.querySelector( '.wtk-kit[data-wtk-kit="' + id + '"]' );
	}

	function isOn( id ) {
		var card = cardFor( id ) || document.querySelector( '[data-wtk-kit="' + id + '"]' );
		return !! card && '1' === card.getAttribute( 'data-on' );
	}

	function nameOf( id ) {
		var card = cardFor( id );
		return card ? card.getAttribute( 'data-name' ) : id;
	}

	// Shows "Not running: it needs X" on every switched-on card whose required kits are off.
	function refreshNeeds() {
		all( '.wtk-kit[data-requires]' ).forEach( function ( card ) {
			var requires = ( card.getAttribute( 'data-requires' ) || '' ).split( ',' ).filter( Boolean );
			var box = card.querySelector( '[data-wtk-needs]' );
			if ( ! requires.length || ! box ) {
				return;
			}
			var missing = requires.filter( function ( id ) {
				return ! isOn( id );
			} );
			var on = '1' === card.getAttribute( 'data-on' );
			box.hidden = ! on || ! missing.length;
			var status = card.querySelector( '[data-wtk-status]' );
			if ( on && status ) {
				status.textContent = missing.length ? t( 'waiting', 'On, waiting' ) : t( 'on', 'On' );
			}
			if ( missing.length ) {
				box.querySelector( '[data-wtk-needs-text]' ).textContent = format( t( 'needs', 'Not running: it needs %s.' ), missing.map( nameOf ).join( ', ' ) );
				var button = box.querySelector( '[data-wtk-enable]' );
				if ( button ) {
					button.setAttribute( 'data-wtk-enable', missing[ 0 ] );
					button.hidden = ! cardFor( missing[ 0 ] ) || '1' !== cardFor( missing[ 0 ] ).getAttribute( 'data-owned' );
				}
			}
		} );
	}

	function setState( id, on, settingsUrl ) {
		all( '[data-wtk-kit="' + id + '"]' ).forEach( function ( card ) {
			card.setAttribute( 'data-on', on ? '1' : '0' );
			all( '[data-wtk-toggle="' + id + '"]', card ).forEach( function ( sw ) {
				sw.setAttribute( 'aria-checked', on ? 'true' : 'false' );
			} );
			var status = card.querySelector( '[data-wtk-status]' );
			if ( status ) {
				status.textContent = on ? t( 'on', 'On' ) : t( 'off', 'Off' );
			}
			var settings = card.querySelector( '[data-wtk-settings]' );
			if ( settings ) {
				if ( on && settingsUrl ) {
					settings.href = settingsUrl;
				}
				settings.hidden = ! on || '#' === settings.getAttribute( 'href' );
			}
		} );
		refreshNeeds();
		applyFilters();
	}

	function toggle( id, on, control ) {
		var reload = control && control.hasAttribute( 'data-wtk-reload' );
		control.setAttribute( 'aria-busy', 'true' );
		if ( 'BUTTON' === control.tagName ) {
			control.disabled = true;
		}
		return post( {
			action: data.toggle.action,
			_ajax_nonce: data.toggle.nonce,
			kit: id,
			on: on ? '1' : '0',
		} ).then( function ( response ) {
			control.removeAttribute( 'aria-busy' );
			control.disabled = false;
			if ( ! response || ! response.success ) {
				var message = response && response.data && response.data.message ? response.data.message : t( 'failed', 'That did not save.' );
				notice( message, 'error' );
				return;
			}
			if ( reload ) {
				window.location.reload();
				return;
			}
			setState( id, !! response.data.on, response.data.settings_url );
			announce( response.data.message );
		} ).catch( function () {
			control.removeAttribute( 'aria-busy' );
			control.disabled = false;
			notice( t( 'failed', 'That did not save.' ), 'error' );
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var sw = event.target.closest( '[data-wtk-toggle]' );
		if ( sw && sw.closest( '.wtk-app' ) ) {
			event.preventDefault();
			if ( 'true' !== sw.getAttribute( 'aria-busy' ) ) {
				toggle( sw.getAttribute( 'data-wtk-toggle' ), 'true' !== sw.getAttribute( 'aria-checked' ), sw );
			}
			return;
		}
		var enable = event.target.closest( '[data-wtk-enable]' );
		if ( enable && enable.closest( '.wtk-app' ) ) {
			event.preventDefault();
			toggle( enable.getAttribute( 'data-wtk-enable' ), true, enable );
		}
	} );

	/* Search and filters -------------------------------------------------------------------- */

	var filters = document.querySelector( '[data-wtk-filters]' );
	var state = { view: filters ? filters.getAttribute( 'data-view' ) || 'all' : 'all', col: 'all', q: '' };

	function matches( card ) {
		var type = card.getAttribute( 'data-type' );
		var owned = '1' === card.getAttribute( 'data-owned' );
		var on = '1' === card.getAttribute( 'data-on' );
		switch ( state.view ) {
			case 'on':
				if ( ! on ) {
					return false;
				}
				break;
			case 'off':
				if ( on || ! owned ) {
					return false;
				}
				break;
			case 'available':
				if ( owned ) {
					return false;
				}
				break;
			case 'free':
				if ( 'pro' === type ) {
					return false;
				}
				break;
			case 'pro':
				if ( 'pro' !== type ) {
					return false;
				}
				break;
		}
		if ( 'all' !== state.col && card.getAttribute( 'data-collection' ) !== state.col ) {
			return false;
		}
		return ! state.q || -1 !== ( card.getAttribute( 'data-search' ) || '' ).indexOf( state.q );
	}

	function applyFilters() {
		var grid = document.querySelector( '[data-wtk-grid]' );
		if ( ! grid ) {
			return;
		}
		var shown = 0;
		var on = 0;
		all( '.wtk-kit', grid ).forEach( function ( card ) {
			var show = matches( card );
			card.hidden = ! show;
			shown += show ? 1 : 0;
			on += '1' === card.getAttribute( 'data-on' ) ? 1 : 0;
		} );
		var count = document.querySelector( '[data-wtk-count]' );
		if ( count ) {
			count.textContent = format( t( 'shown', '%1$s kits shown · %2$s on' ), shown, on );
		}
		var empty = document.querySelector( '[data-wtk-empty]' );
		if ( empty ) {
			empty.hidden = shown > 0;
		}
	}

	if ( filters ) {
		filters.addEventListener( 'click', function ( event ) {
			var chip = event.target.closest( '[data-wtk-view], [data-wtk-col]' );
			if ( ! chip ) {
				return;
			}
			var attr = chip.hasAttribute( 'data-wtk-view' ) ? 'data-wtk-view' : 'data-wtk-col';
			all( '[' + attr + ']', filters ).forEach( function ( other ) {
				other.setAttribute( 'aria-pressed', other === chip ? 'true' : 'false' );
			} );
			state[ 'data-wtk-view' === attr ? 'view' : 'col' ] = chip.getAttribute( attr );
			applyFilters();
		} );
		var search = filters.querySelector( '[data-wtk-search]' );
		if ( search ) {
			search.addEventListener( 'input', function () {
				state.q = search.value.trim().toLowerCase();
				applyFilters();
			} );
		}
		// "Classic" collection when the page is opened from Settings > Classic kits.
		if ( '#wtk-classic' === window.location.hash ) {
			var classic = filters.querySelector( '[data-wtk-col="classic"]' );
			if ( classic ) {
				classic.click();
			}
		}
		applyFilters();
	}

	/* Pro kit install (WooToolkit Pro provides data.install) -------------------------------- */

	function install( button ) {
		var card = button.closest( '.wtk-kit' );
		if ( ! card || ! data.install || ! data.install.action ) {
			return;
		}
		var steps = t( 'steps', [ 'Downloading…', 'Installing…', 'Switching it on…' ] );
		var progress = card.querySelector( '[data-wtk-progress]' );
		var error = card.querySelector( '[data-wtk-error]' );
		var step = 0;
		var name = button.getAttribute( 'data-kit-name' );

		function show() {
			progress.querySelector( '[data-wtk-step]' ).textContent = steps[ step ];
			progress.querySelector( '[data-wtk-step-num]' ).textContent = format( t( 'stepOf', '%1$s of %2$s' ), step + 1, steps.length );
			progress.querySelector( '[data-wtk-bar]' ).style.width = Math.round( ( ( step + 1 ) / steps.length ) * 100 ) + '%';
		}

		error.hidden = true;
		progress.hidden = false;
		button.hidden = true;
		card.querySelector( '[data-wtk-status]' ).textContent = t( 'installing', 'Installing' );
		show();
		// The install is one request; the steps advance while it runs and wait on the last one.
		var timer = window.setInterval( function () {
			if ( step < steps.length - 1 ) {
				step++;
				show();
			}
		}, 1500 );

		post( {
			action: data.install.action,
			_ajax_nonce: data.install.nonce,
			id: button.getAttribute( 'data-store-id' ),
		} ).then( function ( response ) {
			window.clearInterval( timer );
			progress.hidden = true;
			if ( ! response || ! response.success ) {
				error.querySelector( '[data-wtk-error-text]' ).textContent = response && response.data && response.data.message ? response.data.message : t( 'installFail', 'The kit could not be installed.' );
				error.hidden = false;
				error.querySelector( '[data-wtk-retry]' ).onclick = function () {
					install( button );
				};
				card.querySelector( '[data-wtk-status]' ).textContent = '';
				return;
			}
			var result = response.data || {};
			if ( ! result.kit_id ) {
				window.location.reload();
				return;
			}
			becomeSwitchedOn( card, button, result );
			notice( result.message || format( t( 'installed', '%s is installed and switched on.' ), name ), 'success', result.settings_url, t( 'openSet', 'Open its settings' ) );
		} ).catch( function () {
			window.clearInterval( timer );
			progress.hidden = true;
			error.querySelector( '[data-wtk-error-text]' ).textContent = t( 'installFail', 'The kit could not be installed.' );
			error.hidden = false;
			error.querySelector( '[data-wtk-retry]' ).onclick = function () {
				install( button );
			};
		} );
	}

	// Turns an installed Pro kit's card into a normal switched-on card.
	function becomeSwitchedOn( card, button, result ) {
		var id = result.kit_id;
		var template = document.getElementById( 'wtk-switch-template' );
		card.setAttribute( 'data-wtk-kit', id );
		card.setAttribute( 'data-owned', '1' );
		card.setAttribute( 'data-on', '1' );
		if ( template && ! card.querySelector( '[data-wtk-toggle]' ) ) {
			var sw = template.content.firstElementChild.cloneNode( true );
			var heading = card.querySelector( '.wtk-kit__name' );
			sw.setAttribute( 'data-wtk-toggle', id );
			if ( heading ) {
				sw.setAttribute( 'aria-labelledby', heading.id );
			}
			card.querySelector( '.wtk-kit__top' ).appendChild( sw );
		}
		if ( result.settings_url ) {
			var link = document.createElement( 'a' );
			link.className = 'wtk-kit__settings';
			link.href = result.settings_url;
			link.setAttribute( 'data-wtk-settings', '' );
			link.textContent = t( 'settings', 'Settings' );
			card.querySelector( '.wtk-kit__foot' ).insertBefore( link, card.querySelector( '.wtk-kit__foot' ).firstChild );
		}
		button.remove();
		card.querySelector( '[data-wtk-status]' ).textContent = t( 'on', 'On' );
		refreshNeeds();
		applyFilters();
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-wtk-install]' );
		if ( button ) {
			event.preventDefault();
			install( button );
		}
	} );

	/* Dialogs -------------------------------------------------------------------------------- */

	var openModal = null;
	var opener = null;

	function focusables( root ) {
		return all( 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])', root ).filter( function ( el ) {
			return ! el.closest( '[hidden]' );
		} );
	}

	function open( modal, from ) {
		if ( ! modal ) {
			return;
		}
		opener = from || document.activeElement;
		openModal = modal;
		modal.hidden = false;
		document.body.classList.add( 'wtk-modal-open' );
		var items = focusables( modal.querySelector( '.wtk-dialog' ) );
		( items[ 0 ] || modal.querySelector( '.wtk-dialog' ) ).focus();
	}

	function close() {
		if ( ! openModal ) {
			return;
		}
		openModal.hidden = true;
		openModal = null;
		document.body.classList.remove( 'wtk-modal-open' );
		if ( opener && opener.focus ) {
			opener.focus();
		}
	}

	document.addEventListener( 'keydown', function ( event ) {
		if ( ! openModal ) {
			return;
		}
		if ( 'Escape' === event.key ) {
			event.preventDefault();
			close();
			return;
		}
		if ( 'Tab' === event.key ) {
			var items = focusables( openModal.querySelector( '.wtk-dialog' ) );
			if ( ! items.length ) {
				event.preventDefault();
				return;
			}
			var first = items[ 0 ];
			var last = items[ items.length - 1 ];
			if ( event.shiftKey && ( document.activeElement === first || ! openModal.contains( document.activeElement ) ) ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		}
	} );

	document.addEventListener( 'click', function ( event ) {
		if ( openModal && event.target.closest( '[data-wtk-close]' ) ) {
			event.preventDefault();
			close();
			return;
		}
		var trigger = event.target.closest( '[data-wtk-open]' );
		if ( trigger ) {
			event.preventDefault();
			open( document.querySelector( '[data-wtk-modal="' + trigger.getAttribute( 'data-wtk-open' ) + '"]' ), trigger );
			return;
		}
		var getPro = event.target.closest( '[data-wtk-getpro]' );
		var modal = document.querySelector( '[data-wtk-modal="pro"]' );
		if ( getPro && modal ) {
			event.preventDefault();
			modal.querySelector( '[data-wtk-pro-title]' ).textContent = getPro.getAttribute( 'data-kit-name' );
			modal.querySelector( '[data-wtk-pro-desc]' ).textContent = getPro.getAttribute( 'data-desc' );
			var price = getPro.getAttribute( 'data-price' );
			var single = modal.querySelector( '[data-wtk-pro-single]' );
			single.hidden = ! price;
			modal.querySelector( '[data-wtk-pro-price]' ).textContent = price;
			modal.querySelector( '[data-wtk-pro-buy]' ).href = getPro.getAttribute( 'href' );
			open( modal, getPro );
		}
	} );

	/* Kit page tabs ------------------------------------------------------------------------- */

	all( '[data-wtk-tabs]' ).forEach( function ( list ) {
		var tabs = all( '[data-wtk-tab]', list );
		var field = document.querySelector( '[data-wtk-tab-field]' );
		function select( tab, focus ) {
			tabs.forEach( function ( other ) {
				var on = other === tab;
				other.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				other.setAttribute( 'tabindex', on ? '0' : '-1' );
				var panel = document.getElementById( other.getAttribute( 'aria-controls' ) );
				if ( panel ) {
					panel.hidden = ! on;
				}
			} );
			if ( field ) {
				field.value = tab.getAttribute( 'data-wtk-tab' );
			}
			if ( focus ) {
				tab.focus();
			}
		}
		list.addEventListener( 'click', function ( event ) {
			var tab = event.target.closest( '[data-wtk-tab]' );
			if ( tab ) {
				event.preventDefault();
				select( tab );
			}
		} );
		list.addEventListener( 'keydown', function ( event ) {
			var index = tabs.indexOf( document.activeElement );
			if ( -1 === index ) {
				return;
			}
			var next = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 }[ event.key ];
			if ( undefined !== next ) {
				event.preventDefault();
				select( tabs[ ( next + tabs.length ) % tabs.length ], true );
			}
		} );
	} );

	/* Unsaved changes note ------------------------------------------------------------------- */

	all( '[data-wtk-form]' ).forEach( function ( form ) {
		var note = form.querySelector( '[data-wtk-dirty-note]' );
		var dirty = false;
		function mark() {
			if ( ! dirty && note ) {
				note.textContent = note.getAttribute( 'data-dirty' );
			}
			dirty = true;
		}
		form.addEventListener( 'input', mark );
		form.addEventListener( 'change', mark );
		form.addEventListener( 'submit', function () {
			dirty = false;
		} );
		window.addEventListener( 'beforeunload', function ( event ) {
			if ( dirty && note ) {
				event.preventDefault();
				event.returnValue = '';
			}
		} );
	} );

	/* Help: guide search and Copy ------------------------------------------------------------ */

	var guideSearch = document.querySelector( '[data-wtk-guide-search]' );
	if ( guideSearch ) {
		guideSearch.addEventListener( 'input', function () {
			var q = guideSearch.value.trim().toLowerCase();
			var shown = 0;
			all( '[data-wtk-guide]' ).forEach( function ( item ) {
				var show = ! q || -1 !== item.getAttribute( 'data-wtk-guide' ).indexOf( q );
				item.hidden = ! show;
				shown += show ? 1 : 0;
			} );
			var empty = document.querySelector( '[data-wtk-guide-empty]' );
			if ( empty ) {
				empty.hidden = shown > 0;
			}
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-wtk-copy]' );
		if ( ! button ) {
			return;
		}
		var source = document.querySelector( button.getAttribute( 'data-wtk-copy' ) );
		var status = button.parentNode.querySelector( '[data-wtk-copy-status]' );
		if ( ! source ) {
			return;
		}
		function fallback() {
			source.hidden = false;
			source.focus();
			source.select();
			if ( status ) {
				status.textContent = t( 'copyManual', 'Press Ctrl+C to copy.' );
			}
		}
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( source.value ).then( function () {
				button.textContent = t( 'copied', 'Copied' );
				if ( status ) {
					status.textContent = t( 'copied', 'Copied' );
				}
			}, fallback );
		} else {
			fallback();
		}
	} );

	/* WooCommerce help tips on kit pages ----------------------------------------------------- */

	if ( window.jQuery && window.jQuery.fn && window.jQuery.fn.tipTip ) {
		window.jQuery( '.wtk-app .woocommerce-help-tip' ).tipTip( { attribute: 'data-tip', fadeIn: 50, fadeOut: 50, delay: 200 } );
	}

	refreshNeeds();
}() );
