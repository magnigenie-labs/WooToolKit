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
	const KITS = '/wp-admin/admin.php?page=wootoolkit-kits';
	const sw = ( page, id ) => page.locator( `button[role="switch"][data-wtk-toggle="${ id }"]` ).first();
	// Switches a kit through its card and waits for the admin-ajax save.
	const setKit = async ( page, id, on ) => {
		const toggle = sw( page, id );
		if ( ( await toggle.getAttribute( 'aria-checked' ) ) === ( on ? 'true' : 'false' ) ) {
			return;
		}
		await Promise.all( [
			page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) && r.request().method() === 'POST' ),
			toggle.click(),
		] );
		await expect( toggle ).toHaveAttribute( 'aria-checked', on ? 'true' : 'false' );
	};

	test( 'the WooToolkit menu has its pages', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=wootoolkit' );
		await expect( page.getByRole( 'heading', { level: 1, name: "Your store's kits" } ) ).toBeVisible();
		const menu = page.locator( '#toplevel_page_wootoolkit' );
		for ( const label of [ 'Dashboard', 'Kits', 'Settings', 'Help', 'Get Pro' ] ) {
			await expect( menu.getByRole( 'link', { name: label, exact: true } ) ).toHaveCount( 1 );
		}
		await expect( page.locator( '.wtk-header' ) ).toContainText( 'Free' );
	} );

	test( 'the Kits page lists every kit', async ( { page } ) => {
		await page.goto( KITS );
		await expect( page.locator( '.wtk-kit[data-type="free"]' ) ).toHaveCount( 7 );
		await expect( page.locator( '.wtk-kit[data-type="classic"]' ) ).toHaveCount( 9 );
		await expect( page.locator( '.wtk-kit[data-type="pro"]' ).first() ).toBeVisible();
		await page.locator( '[data-wtk-view="pro"]' ).click();
		await expect( page.locator( '.wtk-kit[data-type="free"]' ).first() ).toBeHidden();
	} );

	test( 'old settings tab URLs redirect to the new pages', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=wootoolkit' );
		await expect( page ).toHaveURL( /page=wootoolkit-kits/ );
		await page.goto( '/wp-admin/admin.php?page=wc-settings&tab=wootoolkit&section=catalog-mode' );
		await expect( page ).toHaveURL( /page=wootoolkit-kits&kit=catalog-mode/ );
	} );

	test( 'switching a kit on and off saves straight away', async ( { page } ) => {
		await page.goto( KITS );
		// Start from a known state, so a retry does not depend on the previous attempt.
		await setKit( page, 'accordions', false );
		await setKit( page, 'accordions', true );
		await page.reload();
		await expect( sw( page, 'accordions' ) ).toHaveAttribute( 'aria-checked', 'true' );
		await setKit( page, 'accordions', false );
		await page.reload();
		await expect( sw( page, 'accordions' ) ).toHaveAttribute( 'aria-checked', 'false' );
		// The Classic screen is no longer in the WooCommerce menu.
		await expect( page.locator( '#adminmenu a[href*="page=woo-toolkit-settings"]' ) ).toHaveCount( 0 );
	} );

	test( 'the classic kit settings screen loads while a classic kit is on', async ( { page } ) => {
		await page.goto( KITS );
		await setKit( page, 'empty-cart', true );
		const response = await page.goto( '/wp-admin/admin.php?page=woo-toolkit-settings' );
		expect( response?.status() ).toBe( 200 );
		await expect( page.locator( '.nav-tab-wrapper' ) ).toContainText( 'Empty Cart' );
	} );

	test( 'a kit settings page saves', async ( { page } ) => {
		await page.goto( `${ KITS }&kit=catalog-mode` );
		await expect( page.getByRole( 'heading', { level: 1, name: 'Catalog mode' } ) ).toBeVisible();
		await page.getByRole( 'button', { name: 'Save changes' } ).click();
		await expect( page.locator( '.wtk-notice' ).first() ).toContainText( 'Settings saved' );
	} );

	test( 'settings and help pages load', async ( { page } ) => {
		for ( const section of [ 'general', 'access', 'classic', 'privacy', 'import', 'advanced' ] ) {
			const response = await page.goto( `/wp-admin/admin.php?page=wootoolkit-settings&section=${ section }` );
			expect( response?.status() ).toBe( 200 );
			await expect( page.locator( '.wtk-sidenav [aria-current="page"]' ) ).toHaveCount( 1 );
		}
		await page.goto( '/wp-admin/admin.php?page=wootoolkit-help' );
		await expect( page.getByRole( 'heading', { name: 'System details' } ) ).toBeVisible();
	} );
} );
