<?php
/**
 * The instant kit switches (admin-ajax wootoolkit_toggle_kit).
 *
 * @package WooToolkit
 */

use WooToolkit\Admin\KitsPage;
use WooToolkit\Kit\HasRequirements;
use WooToolkit\Kit\Registry;

/**
 * Kit switch AJAX tests.
 *
 * @group ajax
 */
class Test_Admin_Ajax extends WP_Ajax_UnitTestCase {

	/**
	 * Registry used by the handler.
	 *
	 * @var Registry
	 */
	private $registry;

	public function set_up() {
		parent::set_up();
		$this->registry = new Registry();
		$this->registry->register( new WooToolkit_Test_Kit( 'base' ) );
		$this->registry->register(
			new class( 'tiers' ) extends WooToolkit_Test_Kit implements HasRequirements {
				public function requires(): array {
					return array( 'base' );
				}
			}
		);
		$this->registry->set_active( array() );
		remove_all_actions( 'wp_ajax_' . KitsPage::TOGGLE );
		add_action( 'wp_ajax_' . KitsPage::TOGGLE, array( new KitsPage( $this->registry ), 'ajax_toggle' ) );
	}

	private function call( array $post ): array {
		$_POST = $post;
		try {
			$this->_handleAjax( KitsPage::TOGGLE );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		}
		$response = json_decode( $this->_last_response, true );

		$this->_last_response = '';
		return is_array( $response ) ? $response : array();
	}

	public function test_switching_a_kit_on_saves_straight_away_and_reports_missing_kits() {
		$this->_setRole( 'administrator' );
		$response = $this->call(
			array(
				'_ajax_nonce' => wp_create_nonce( KitsPage::TOGGLE ),
				'kit'         => 'tiers',
				'on'          => '1',
			)
		);
		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['data']['on'] );
		$this->assertSame( 'waiting', $response['data']['state'] );
		$this->assertSame( array( 'base' ), wp_list_pluck( $response['data']['missing'], 'id' ) );
		$this->assertSame( array( 'tiers' ), $response['data']['waiting'] );
		$this->assertTrue( $this->registry->is_active( 'tiers' ) );

		$response = $this->call(
			array(
				'_ajax_nonce' => wp_create_nonce( KitsPage::TOGGLE ),
				'kit'         => 'base',
				'on'          => '1',
			)
		);
		$this->assertSame( 'on', $response['data']['state'] );
		$this->assertSame( array(), $response['data']['waiting'] );
		$this->assertStringContainsString( 'kit=base', $response['data']['settings_url'] );

		$response = $this->call(
			array(
				'_ajax_nonce' => wp_create_nonce( KitsPage::TOGGLE ),
				'kit'         => 'base',
				'on'          => '0',
			)
		);
		$this->assertFalse( $response['data']['on'] );
		$this->assertSame( array( 'tiers' ), $response['data']['waiting'] );
		$this->assertSame( 'no', get_option( Registry::OPTION )['base'] );
	}

	public function test_switch_keeps_states_of_kits_that_are_not_registered() {
		$this->_setRole( 'administrator' );
		update_option( Registry::OPTION, array( 'some-pro-kit' => 'yes' ) );
		$this->call(
			array(
				'_ajax_nonce' => wp_create_nonce( KitsPage::TOGGLE ),
				'kit'         => 'base',
				'on'          => '1',
			)
		);
		$this->assertSame( 'yes', get_option( Registry::OPTION )['some-pro-kit'] );
	}

	public function test_bad_nonce_is_refused() {
		$this->_setRole( 'administrator' );
		$response = $this->call(
			array(
				'_ajax_nonce' => 'nope',
				'kit'         => 'base',
				'on'          => '1',
			)
		);
		$this->assertFalse( $response['success'] );
		$this->assertFalse( $this->registry->is_active( 'base' ) );
	}

	public function test_users_without_the_capability_are_refused() {
		$this->_setRole( 'subscriber' );
		$response = $this->call(
			array(
				'_ajax_nonce' => wp_create_nonce( KitsPage::TOGGLE ),
				'kit'         => 'base',
				'on'          => '1',
			)
		);
		$this->assertFalse( $response['success'] );
		$this->assertFalse( $this->registry->is_active( 'base' ) );
	}

	public function test_unknown_kit_is_refused() {
		$this->_setRole( 'administrator' );
		$response = $this->call(
			array(
				'_ajax_nonce' => wp_create_nonce( KitsPage::TOGGLE ),
				'kit'         => 'nope',
				'on'          => '1',
			)
		);
		$this->assertFalse( $response['success'] );
	}
}
