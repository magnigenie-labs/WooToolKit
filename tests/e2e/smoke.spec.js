// @ts-check
const { test, expect } = require( '@playwright/test' );

test.describe( 'storefront (logged out)', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test( 'shop page lists the seeded product with WooToolkit active', async ( { page } ) => {
		const response = await page.goto( '/shop/' );
		expect( response?.status() ).toBe( 200 );
		await expect( page.getByRole( 'heading', { name: 'E2E Product' } ) ).toBeVisible();
		await expect( page.locator( 'body' ) ).not.toContainText( 'critical error' );
	} );
} );

test.describe( 'admin', () => {
	test( 'Installed Kits screen lists every kit', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=woo-toolkit' );
		await expect( page.getByRole( 'heading', { name: 'Toolkits' } ) ).toBeVisible();
		await expect( page.locator( '#the-list > tr' ) ).toHaveCount( 10 );
	} );

	test( 'deactivating and reactivating a kit works through the nonce links', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=woo-toolkit' );
		const row = page.locator( '#the-list > tr', { hasText: 'Accordion' } ).first();
		await row.getByRole( 'link', { name: 'Deactivate', exact: true } ).click();
		await expect( page.locator( '#message' ) ).toContainText( 'Kit deactivated successfully.' );
		await page.locator( '#the-list > tr', { hasText: 'Accordion' } ).first().getByRole( 'link', { name: 'Activate', exact: true } ).click();
		await expect( page.locator( '#message' ) ).toContainText( 'Kit activated successfully.' );
	} );

	test( 'a forged kit action link without a nonce is rejected', async ( { page } ) => {
		const plugin = encodeURIComponent( 'wootoolkit-accordions/wootoolkit-accordions.php' );
		await page.goto( `/wp-admin/admin.php?page=woo-toolkit&action=deactivate&plugin=${ plugin }` );
		await expect( page.locator( 'body' ) ).toContainText( 'The link you followed has expired' );
	} );
} );
