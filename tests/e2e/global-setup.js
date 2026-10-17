// Logs in once as the wp-env admin and saves the session for admin tests.
// wp-env's local development credentials (admin / password) are its documented defaults.
const { chromium } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );

module.exports = async ( config ) => {
	const baseURL = config.projects[ 0 ].use.baseURL;
	const statePath = path.join( __dirname, '.auth', 'admin.json' );
	fs.mkdirSync( path.dirname( statePath ), { recursive: true } );

	const browser = await chromium.launch();
	const page = await browser.newPage( { baseURL } );
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', process.env.WP_USERNAME || 'admin' );
	await page.fill( '#user_pass', process.env.WP_PASSWORD || 'password' );
	await Promise.all( [ page.waitForURL( /wp-admin/ ), page.click( '#wp-submit' ) ] );
	await page.context().storageState( { path: statePath } );
	await browser.close();
};
