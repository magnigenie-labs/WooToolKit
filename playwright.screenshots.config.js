// @ts-check
// WordPress.org screenshots: `npx playwright test -c playwright.screenshots.config.js` (CI, branch "screenshots").
const base = require( './playwright.config' );

module.exports = {
	...base,
	testDir: './tests/screenshots',
	retries: 0,
	use: { ...base.use, viewport: { width: 1280, height: 900 }, deviceScaleFactor: 1 },
	projects: [ { name: 'chromium', use: { viewport: { width: 1280, height: 900 } } } ],
};
