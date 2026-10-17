// @ts-check
const { test, expect } = require( '@playwright/test' );

// Seeded by bin/e2e-seed.sh: "E2E Product" costs 19.00 retail and 12.00 wholesale.
test.describe( 'wholesale prices', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test( 'guests see the retail price', async ( { page } ) => {
		await page.goto( '/product/e2e-product/' );
		await expect( page.locator( 'main' ) ).toContainText( '19.00' );
		await expect( page.locator( 'main' ) ).not.toContainText( '12.00' );
	} );

	test( 'wholesale customers see and pay the wholesale price', async ( { page } ) => {
		await page.goto( '/wp-login.php' );
		await page.fill( '#user_login', 'wholesale' );
		await page.fill( '#user_pass', 'wholesale-e2e-pass' );
		await page.click( '#wp-submit' );
		await page.waitForLoadState( 'load' );

		await page.goto( '/product/e2e-product/' );
		const main = page.locator( 'main' );
		await expect( main ).toContainText( '12.00' );
		await expect( main.locator( 'del' ).first() ).toContainText( '19.00' );

		await main.getByRole( 'button', { name: /add to cart/i } ).first().click();
		await page.waitForLoadState( 'networkidle' );

		// WooCommerce 11's default cart is the Cart block, which loads prices from the Store API.
		// The cart may still show 19.00 crossed out next to the item; what matters is the total charged.
		await page.goto( '/cart/' );
		const total = page.locator( '.wc-block-components-totals-footer-item, .order-total' ).first();
		await expect( total ).toContainText( '12.00' );
	} );
} );
