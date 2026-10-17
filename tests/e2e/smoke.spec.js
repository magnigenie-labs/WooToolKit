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
		const accordionRow = () => page.locator( '#the-list > tr', { hasText: 'Accordions' } ).first();
		const clickAction = async ( name, message ) => {
			await Promise.all( [
				page.waitForURL( new RegExp( `message=${ message }` ), { timeout: 30000 } ),
				accordionRow().getByRole( 'link', { name, exact: true } ).click(),
			] );
		};

		await page.goto( '/wp-admin/admin.php?page=woo-toolkit' );
		// Start from a known state, so a retry does not depend on what the previous attempt did.
		if ( await accordionRow().getByRole( 'link', { name: 'Activate', exact: true } ).count() ) {
			await clickAction( 'Activate', 1 );
		}

		await clickAction( 'Deactivate', 2 );
		await expect( page.locator( '#message' ) ).toContainText( 'Kit deactivated successfully.', { timeout: 15000 } );
		await expect( accordionRow() ).toHaveClass( /inactive/ );

		await clickAction( 'Activate', 1 );
		await expect( page.locator( '#message' ) ).toContainText( 'Kit activated successfully.', { timeout: 15000 } );
		await expect( accordionRow() ).toHaveClass( /(^|\s)active(\s|$)/ );
	} );

	test( 'a forged kit action link without a nonce is rejected', async ( { page } ) => {
		const plugin = encodeURIComponent( 'wootoolkit-accordions/wootoolkit-accordions.php' );
		await page.goto( `/wp-admin/admin.php?page=woo-toolkit&action=deactivate&plugin=${ plugin }` );
		await expect( page.locator( 'body' ) ).toContainText( 'The link you followed has expired' );
	} );
} );
