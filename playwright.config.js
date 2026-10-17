// @ts-check
const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	timeout: 90000,
	expect: { timeout: 15000 },
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: process.env.CI ? [ [ 'github' ], [ 'html', { open: 'never' } ] ] : 'list',
	globalSetup: require.resolve( './tests/e2e/global-setup.js' ),
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		storageState: 'tests/e2e/.auth/admin.json',
		trace: 'retain-on-failure',
	},
	projects: [ { name: 'chromium', use: { ...devices[ 'Desktop Chrome' ] } } ],
} );
