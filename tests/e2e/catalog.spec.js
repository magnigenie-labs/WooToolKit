// @ts-check
const { test, expect } = require( '@playwright/test' );

// Seeded by bin/e2e-seed.sh: "E2E Catalog Product" (29.00) is in catalog mode for everyone,
// with the price replaced by "Call for price" and an "Ask for a price" button.
test.describe( 'catalog mode', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	const productId = async ( request ) => {
		const res = await request.get( '/wp-json/wc/store/v1/products?slug=e2e-catalog-product' );
		expect( res.ok() ).toBeTruthy();
		const [ product ] = await res.json();
		return product;
	};

	test( 'the product page shows no price and no add-to-cart form', async ( { page } ) => {
		await page.goto( '/product/e2e-catalog-product/' );
		const main = page.locator( 'main' );
		await expect( main ).toContainText( 'Call for price' );
		await expect( main ).not.toContainText( '29.00' );
		await expect( main.getByRole( 'button', { name: /add to cart/i } ) ).toHaveCount( 0 );
		await expect( main.getByRole( 'link', { name: 'Ask for a price' } ) ).toHaveAttribute( 'href', 'https://example.test/contact' );
	} );

	test( 'the shop keeps selling other products', async ( { page } ) => {
		await page.goto( '/shop/' );
		await expect( page.locator( 'main' ) ).toContainText( '19.00' );
		await expect( page.locator( 'main' ) ).not.toContainText( '29.00' );
	} );

	test( 'a direct add-to-cart link is refused', async ( { page, request } ) => {
		const product = await productId( request );
		await page.goto( `/?add-to-cart=${ product.id }` );
		await page.goto( '/wp-json/wc/store/v1/cart' );
		const cart = JSON.parse( await page.locator( 'body' ).innerText() );
		expect( cart.items_count ).toBe( 0 );
	} );

	test( 'the Store API refuses it and does not reveal the price', async ( { request } ) => {
		const product = await productId( request );
		expect( product.is_purchasable ).toBe( false );
		expect( product.prices.price ).toBe( '' );
		expect( JSON.stringify( product ) ).not.toContain( '2900' );

		const cart = await request.get( '/wp-json/wc/store/v1/cart' );
		const nonce = cart.headers().nonce;
		const add = await request.post( '/wp-json/wc/store/v1/cart/add-item', {
			headers: { Nonce: nonce },
			data: { id: product.id, quantity: 1 },
		} );
		expect( add.ok() ).toBe( false );
		const after = await ( await request.get( '/wp-json/wc/store/v1/cart' ) ).json();
		expect( after.items_count ).toBe( 0 );
	} );
} );
