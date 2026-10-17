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
	const TAB = '/wp-admin/admin.php?page=wc-settings&tab=wootoolkit';
	const save = async ( page ) => {
		await page.locator( 'button[name="save"]' ).click();
		await expect( page.locator( '#message, .updated, .notice-success' ).first() ).toContainText( 'Your settings have been saved.' );
	};

	test( 'the WooToolkit settings tab lists every kit', async ( { page } ) => {
		await page.goto( TAB );
		await expect( page.locator( 'input[type="checkbox"][name^="wootoolkit_active_kits["]' ) ).toHaveCount( 12 );
	} );

	test( 'switching a kit on and off saves through WooCommerce settings', async ( { page } ) => {
		const box = page.locator( 'input[name="wootoolkit_active_kits[accordions]"]' );
		const classicMenu = page.locator( '#adminmenu a[href*="page=woo-toolkit-settings"]' );

		await page.goto( TAB );
		// Start from a known state, so a retry does not depend on the previous attempt.
		if ( await box.isChecked() ) {
			await box.uncheck();
			await save( page );
		}
		await expect( classicMenu ).toHaveCount( 0 );

		await box.check();
		await save( page );
		await expect( box ).toBeChecked();
		await expect( classicMenu ).toHaveCount( 1 );

		await box.uncheck();
		await save( page );
		await expect( box ).not.toBeChecked();
		await expect( classicMenu ).toHaveCount( 0 );
	} );

	test( 'the classic kit settings screen loads while a classic kit is on', async ( { page } ) => {
		await page.goto( TAB );
		const box = page.locator( 'input[name="wootoolkit_active_kits[empty-cart]"]' );
		if ( ! ( await box.isChecked() ) ) {
			await box.check();
			await save( page );
		}
		const response = await page.goto( '/wp-admin/admin.php?page=woo-toolkit-settings' );
		expect( response?.status() ).toBe( 200 );
		await expect( page.locator( '.nav-tab-wrapper' ) ).toContainText( 'Empty Cart' );
	} );
} );
