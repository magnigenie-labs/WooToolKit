#!/usr/bin/env node
// Resolves the test matrix from the WordPress.org APIs so CI follows new releases automatically:
//   wp:    latest and previous WordPress branches (newest patch of each)
//   wc:    latest and previous WooCommerce minors (newest patch of each)
//   wc_rc: newest WooCommerce beta/RC that is newer than the latest stable, or "" if none
// Prints JSON; with GITHUB_OUTPUT set it also writes `matrix`, `wc_rc` and `latest` outputs.
import { appendFileSync } from 'node:fs';

const PHP_VERSIONS = [ '7.4', '8.1', '8.4' ];

async function getJson( url ) {
	const res = await fetch( url );
	if ( ! res.ok ) {
		throw new Error( `${ url } -> HTTP ${ res.status }` );
	}
	return res.json();
}

// Semver-ish compare that orders 1.2.0-beta.1 < 1.2.0-rc.1 < 1.2.0.
function parse( v ) {
	const m = v.match( /^(\d+)\.(\d+)(?:\.(\d+))?(?:-(beta|rc)\.?(\d+)?)?$/ );
	if ( ! m ) {
		return null;
	}
	const pre = m[ 4 ] === 'beta' ? 0 : m[ 4 ] === 'rc' ? 1 : 2;
	return [ +m[ 1 ], +m[ 2 ], +( m[ 3 ] || 0 ), pre, +( m[ 5 ] || 0 ) ];
}
function compare( a, b ) {
	const pa = parse( a );
	const pb = parse( b );
	for ( let i = 0; i < 5; i++ ) {
		if ( pa[ i ] !== pb[ i ] ) {
			return pa[ i ] - pb[ i ];
		}
	}
	return 0;
}
const isStable = ( v ) => parse( v ) && parse( v )[ 3 ] === 2;
const branch = ( v ) => v.split( '.' ).slice( 0, 2 ).join( '.' );

function newestPerBranch( versions, count ) {
	const byBranch = new Map();
	for ( const v of versions.filter( isStable ).sort( compare ) ) {
		byBranch.set( branch( v ), v );
	}
	return [ ...byBranch.values() ].sort( compare ).slice( -count ).reverse();
}

const wcInfo = await getJson(
	'https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request[slug]=woocommerce&request[fields][versions]=1'
);
const wcVersions = Object.keys( wcInfo.versions ).filter( parse );
const wc = newestPerBranch( wcVersions, 2 );
const wcRc = wcVersions.filter( ( v ) => ! isStable( v ) && compare( v, wc[ 0 ] ) > 0 ).sort( compare ).pop() || '';

const wpCheck = await getJson( 'https://api.wordpress.org/core/version-check/1.7/' );
const wp = newestPerBranch( wpCheck.offers.map( ( o ) => o.version ), 2 );

const include = [];
for ( const php of PHP_VERSIONS ) {
	for ( const w of wp ) {
		for ( const c of wc ) {
			include.push( { php, wp: w, wc: c } );
		}
	}
}

const result = { matrix: { include }, wc_rc: wcRc, latest: { php: '8.4', wp: wp[ 0 ], wc: wc[ 0 ] } };
console.log( JSON.stringify( result, null, 2 ) );

if ( process.env.GITHUB_OUTPUT ) {
	appendFileSync( process.env.GITHUB_OUTPUT, `matrix=${ JSON.stringify( result.matrix ) }\n` );
	appendFileSync( process.env.GITHUB_OUTPUT, `wc_rc=${ wcRc }\n` );
	appendFileSync( process.env.GITHUB_OUTPUT, `latest=${ JSON.stringify( result.latest ) }\n` );
}
