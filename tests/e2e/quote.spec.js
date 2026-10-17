// @ts-check
const { test, expect } = require( '@playwright/test' );

// Seeded by bin/e2e-seed.sh: Request a quote is on, /quote/ holds the Quote block, and Check payments is enabled.
// A guest requests a quote, the admin sends it, and the guest pays through WooCommerce's pay-for-order page.
test( 'quote: request, send, pay', async ( { browser, page: admin }, testInfo ) => {
	// Contexts made from `browser` do not inherit the config's baseURL or the admin login.
	const guest = await browser.newContext( { baseURL: testInfo.project.use.baseURL, storageState: { cookies: [], origins: [] } } );
	const shopper = await guest.newPage();

	await shopper.goto( '/product/e2e-product/' );
	await shopper.locator( 'main' ).getByRole( 'button', { name: 'Add to quote' } ).click();
	await expect( shopper.locator( 'body' ) ).toContainText( 'has been added to your quote' );

	await shopper.goto( '/quote/' );
	await expect( shopper.locator( '.wootoolkit-quote-list' ) ).toContainText( 'E2E Product' );
	await shopper.fill( '#quote_first_name', 'Quinn' );
	await shopper.fill( '#quote_email', 'quinn@example.test' );
	await shopper.fill( '#quote_message', 'Price for 100 units, please.' );
	await shopper.getByRole( 'button', { name: 'Send quote request' } ).click();
	const received = shopper.locator( '.wootoolkit-quote-received' );
	await expect( received ).toContainText( 'We have received your quote request #' );
	const orderId = ( ( await received.innerText() ).match( /#(\d+)/ ) || [] )[ 1 ];
	expect( orderId ).toBeTruthy();

	// The admin (logged in by global-setup) sends the quote from the order screen.
	await admin.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }` );
	await expect( admin.locator( '#order_status' ) ).toHaveValue( 'wc-wtk-quote' );
	await admin.locator( 'select[name="wc_order_action"]' ).selectOption( 'wootoolkit_send_quote' );
	await admin.locator( 'button.save_order' ).click();
	await expect( admin.locator( '#order_status' ) ).toHaveValue( 'wc-wtk-quote-sent' );
	const payUrl = await admin.getByRole( 'link', { name: /Customer payment page/ } ).getAttribute( 'href' );
	expect( payUrl ).toBeTruthy();

	// The shopper pays. Guests confirm their email address first.
	await shopper.goto( /** @type {string} */ ( payUrl ) );
	const verify = shopper.locator( 'form.woocommerce-verify-email' );
	if ( await verify.count() ) {
		await verify.locator( 'input[type="email"]' ).fill( 'quinn@example.test' );
		await verify.locator( 'button[type="submit"]' ).click();
	}
	const cheque = shopper.locator( '#payment_method_cheque' );
	if ( await cheque.isVisible() ) {
		await cheque.check();
	}
	await shopper.locator( '#place_order' ).click();
	await expect( shopper.locator( 'body' ) ).toContainText( /order has been received|Thank you/i );

	await admin.reload();
	await expect( admin.locator( '#order_status' ) ).toHaveValue( 'wc-on-hold' );
	await guest.close();
} );
