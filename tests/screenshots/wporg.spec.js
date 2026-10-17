// @ts-check
// Captures assets/screenshot-N.png for the WordPress.org listing (captions are in readme.txt).
const { test, expect } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );
const fs = require( 'fs' );

const OUT = 'wporg-assets';
fs.mkdirSync( OUT, { recursive: true } );
const shot = ( n ) => `${ OUT }/screenshot-${ n }.png`;
const wp = ( args ) => execSync( `npx wp-env run cli wp ${ args }`, { encoding: 'utf8' } ).trim();
const id = ( slug ) => wp( `post list --post_type=product --name=${ slug } --field=ID` );

test.describe.configure( { mode: 'serial' } );

// Admin screens: a tall window, so element screenshots are not stitched together.
const tall = async ( page ) => page.setViewportSize( { width: 1280, height: 1800 } );

test( '1 kits screen', async ( { page } ) => {
	await tall( page );
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=wootoolkit' );
	await page.locator( '#wpbody-content' ).screenshot( { path: shot( 1 ) } );
} );

test( '2 wholesale price', async ( { browser }, info ) => {
	const ctx = await browser.newContext( { baseURL: info.project.use.baseURL, viewport: { width: 1280, height: 900 }, storageState: { cookies: [], origins: [] } } );
	const page = await ctx.newPage();
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', 'wholesale' );
	await page.fill( '#user_pass', 'wholesale-e2e-pass' );
	await Promise.all( [ page.waitForLoadState( 'load' ), page.click( '#wp-submit' ) ] );
	await page.goto( '/product/industrial-work-gloves/' );
	await expect( page.locator( 'main del' ).first() ).toContainText( '19.00' );
	await page.screenshot( { path: shot( 2 ) } );
	await ctx.close();
} );

test( '3 wholesale fields', async ( { page } ) => {
	await tall( page );
	await page.goto( `/wp-admin/post.php?post=${ id( 'industrial-work-gloves' ) }&action=edit` );
	await page.locator( '#woocommerce-product-data' ).screenshot( { path: shot( 3 ) } );
} );

test( '4 catalog settings', async ( { page } ) => {
	await tall( page );
	await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=wootoolkit&section=catalog-mode' );
	await page.locator( '#wpbody-content' ).screenshot( { path: shot( 4 ) } );
} );

test( '5 and 6 quote', async ( { browser }, info ) => {
	const ctx = await browser.newContext( { baseURL: info.project.use.baseURL, viewport: { width: 1280, height: 900 }, storageState: { cookies: [], origins: [] } } );
	const page = await ctx.newPage();
	await page.goto( '/product/branded-hard-hat/' );
	await expect( page.locator( 'main' ) ).toContainText( 'Price on request' );
	await page.screenshot( { path: shot( 5 ) } );
	await page.locator( 'main .wootoolkit-quote-form' ).getByRole( 'button', { name: 'Add to quote' } ).click();
	await page.goto( '/product/safety-goggles/' );
	await page.locator( 'main' ).getByRole( 'button', { name: 'Add to quote' } ).click();
	await page.goto( '/quote/' );
	await page.fill( '#quote_first_name', 'Jordan' );
	await page.fill( '#quote_last_name', 'Lee' );
	await page.fill( '#quote_email', 'jordan@example.test' );
	await page.fill( '#quote_company', 'Lee Construction' );
	await page.fill( '#quote_message', 'We need 200 hard hats with our logo, and 50 pairs of goggles. Delivery to Austin, TX.' );
	await page.screenshot( { path: shot( 6 ), fullPage: true } );
	await page.getByRole( 'button', { name: 'Send quote request' } ).click();
	await expect( page.locator( '.wootoolkit-quote-received' ) ).toBeVisible();
	await ctx.close();
} );

test( '7 quote order and 8 email', async ( { page } ) => {
	const orderId = wp( 'wc shop_order list --status=wtk-quote --field=id --user=admin' ).split( /\s+/ )[ 0 ];
	await tall( page );
	await page.goto( `/wp-admin/admin.php?page=wc-orders&action=edit&id=${ orderId }` );
	await page.locator( 'select[name="wc_order_action"]' ).selectOption( 'wootoolkit_send_quote' );
	await page.locator( '#wpbody-content' ).screenshot( { path: shot( 7 ) } );

	await page.locator( 'button.save_order' ).click();
	await expect( page.locator( '#order_status' ) ).toHaveValue( 'wc-wtk-quote-sent' );
	const html = wp( `eval '$e = WC()->mailer()->get_emails()["WooToolkit_Customer_Quote"]; $e->object = wc_get_order( ${ orderId } ); echo $e->style_inline( $e->get_content_html() );'` );
	await page.setViewportSize( { width: 760, height: 900 } );
	await page.setContent( html );
	await page.screenshot( { path: shot( 8 ), fullPage: true } );
} );
