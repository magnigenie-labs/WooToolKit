<?php
/**
 * Regression tests for the 1.0.3 kit activation fixes (CSRF, capability, allowlist).
 *
 * @package WooToolkit
 */

/**
 * Kit activation request handling.
 */
class Test_Kit_Actions extends WP_UnitTestCase {

	private $kit = 'wootoolkit-accordions/wootoolkit-accordions.php';

	public function set_up() {
		parent::set_up();
		$GLOBALS['wootoolkit']->install();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( 'wp_redirect', array( $this, 'throw_redirect' ), 9999 );
	}

	public function tear_down() {
		remove_filter( 'wp_redirect', array( $this, 'throw_redirect' ), 9999 );
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	public function throw_redirect( $location ) {
		throw new WooToolkit_Test_Redirect( $location ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	private function request( array $get ) {
		$_GET     = $get;
		$_REQUEST = $get;
		$GLOBALS['wootoolkit']->handle_kit_actions();
	}

	private function active_kits() {
		return (array) get_option( '_woo_active_kits' );
	}

	public function test_deactivate_without_nonce_is_refused() {
		$this->expectException( 'WPDieException' );
		try {
			$this->request(
				array(
					'page'   => 'woo-toolkit',
					'action' => 'deactivate',
					'plugin' => $this->kit,
				)
			);
		} finally {
			$this->assertContains( $this->kit, $this->active_kits() );
		}
	}

	public function test_deactivate_with_nonce_works_and_redirects() {
		try {
			$this->request(
				array(
					'page'     => 'woo-toolkit',
					'action'   => 'deactivate',
					'plugin'   => $this->kit,
					'_wpnonce' => wp_create_nonce( 'wootoolkit_deactivate_' . $this->kit ),
				)
			);
			$this->fail( 'Expected a redirect.' );
		} catch ( WooToolkit_Test_Redirect $redirect ) {
			$this->assertStringContainsString( 'message=2', $redirect->getMessage() );
		}
		$this->assertNotContains( $this->kit, $this->active_kits() );
	}

	public function test_unknown_kit_key_is_ignored() {
		$evil = '../../../wp-config.php';
		$this->request(
			array(
				'page'     => 'woo-toolkit',
				'action'   => 'activate',
				'plugin'   => $evil,
				'_wpnonce' => wp_create_nonce( 'wootoolkit_activate_' . $evil ),
			)
		);
		$this->assertNotContains( $evil, $this->active_kits() );
	}

	public function test_kit_status_is_sanitised_in_redirect() {
		try {
			$this->request(
				array(
					'page'       => 'woo-toolkit',
					'action'     => 'deactivate',
					'plugin'     => $this->kit,
					'kit_status' => '"><script>alert(1)</script>',
					'_wpnonce'   => wp_create_nonce( 'wootoolkit_deactivate_' . $this->kit ),
				)
			);
			$this->fail( 'Expected a redirect.' );
		} catch ( WooToolkit_Test_Redirect $redirect ) {
			$this->assertStringNotContainsString( '<script', $redirect->getMessage() );
			$this->assertStringContainsString( 'kit_status=all', $redirect->getMessage() );
		}
	}

	public function test_non_admin_cannot_toggle_kits() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->expectException( 'WPDieException' );
		try {
			$this->request(
				array(
					'page'     => 'woo-toolkit',
					'action'   => 'deactivate',
					'plugin'   => $this->kit,
					'_wpnonce' => wp_create_nonce( 'wootoolkit_deactivate_' . $this->kit ),
				)
			);
		} finally {
			$this->assertContains( $this->kit, $this->active_kits() );
		}
	}
}
