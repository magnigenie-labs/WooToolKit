<?php
/**
 * Request a quote on the storefront: buttons, the quote page and its form handlers.
 *
 * @package WooToolkit
 */

namespace WooToolkit\Kits\RequestAQuote;

defined( 'ABSPATH' ) || exit;

/**
 * "Add to quote" buttons, the [wootoolkit_quote] shortcode and wootoolkit/quote block, and the
 * add / update / remove / submit requests.
 */
final class Frontend {

	const OPT_PAGE            = 'wootoolkit_quote_page_id';
	const OPT_BUTTON_TEXT     = 'wootoolkit_quote_button_text';
	const OPT_ON_PURCHASABLE  = 'wootoolkit_quote_on_purchasable';
	const NONCE               = 'wootoolkit_quote';
	const HONEYPOT            = 'wootoolkit_quote_website';

	/**
	 * Hooks the storefront.
	 */
	public static function hooks(): void {
		add_action( 'wp_loaded', array( self::class, 'handle_request' ), 15 ); // Before WooCommerce's add-to-cart handler (20).
		add_action( 'woocommerce_after_add_to_cart_button', array( self::class, 'single_button' ) );
		add_shortcode( 'wootoolkit_quote', array( self::class, 'render_page' ) );
		add_action( 'admin_init', array( self::class, 'maybe_create_page' ) );
		self::register_block();

		// Catalog mode kit: "Add to quote" as the replacement button.
		add_filter( 'wootoolkit_catalog_button_types', array( self::class, 'catalog_button_type' ) );
		add_filter( 'wootoolkit_catalog_button_html', array( self::class, 'catalog_button_html' ), 10, 4 );
	}

	/**
	 * The quote page URL, empty when there is none.
	 */
	public static function page_url(): string {
		$page_id = (int) get_option( self::OPT_PAGE, 0 );
		return $page_id && 'publish' === get_post_status( $page_id ) ? (string) get_permalink( $page_id ) : '';
	}

	/**
	 * Button text.
	 */
	public static function button_text(): string {
		$text = (string) get_option( self::OPT_BUTTON_TEXT, '' );
		return '' === $text ? __( 'Add to quote', 'wootoolkit' ) : $text;
	}

	/**
	 * Creates the quote page the first time the kit runs in wp-admin. Never recreates it, so a page
	 * the owner deleted stays deleted (they can choose another in the settings).
	 */
	public static function maybe_create_page(): void {
		if ( false !== get_option( self::OPT_PAGE, false ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$page_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Quote', 'wootoolkit' ),
				'post_name'    => 'quote',
				'post_content' => '<!-- wp:wootoolkit/quote /-->',
			)
		);
		update_option( self::OPT_PAGE, is_int( $page_id ) ? $page_id : 0 );
	}

	/**
	 * Registers the Quote block (rendered on the server; the editor shows a placeholder).
	 */
	private static function register_block(): void {
		wp_register_script(
			'wootoolkit-quote-block',
			plugins_url( 'assets/js/quote-block.js', WOOTOOLKIT_FILE ),
			array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wp-block-editor' ),
			WOOTOOLKIT_VERSION,
			true
		);
		register_block_type(
			'wootoolkit/quote',
			array(
				'api_version'     => '3',
				'editor_script'   => 'wootoolkit-quote-block',
				'render_callback' => array( self::class, 'render_page' ),
				'supports'        => array( 'html' => false ),
			)
		);
	}

	/**
	 * Routes the quote requests. Each one redirects, so a reload does not repeat it.
	 */
	public static function handle_request(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- adding to the list works like WooCommerce's add-to-cart links (no nonce, harmless); the other requests check a nonce below.
		if ( isset( $_REQUEST['wootoolkit_add_to_quote'] ) ) {
			unset( $_REQUEST['add-to-cart'], $_POST['add-to-cart'], $_GET['add-to-cart'] );
			self::process_add( wp_unslash( $_REQUEST ) );
			$product = wc_get_product( absint( $_REQUEST['wootoolkit_add_to_quote'] ) );
			$back    = wp_get_referer() ? wp_get_referer() : ( $product ? $product->get_permalink() : home_url( '/' ) );
			self::redirect( remove_query_arg( 'wootoolkit_add_to_quote', $back ) );
		}
		$action = isset( $_REQUEST['wootoolkit_quote_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['wootoolkit_quote_action'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification
		if ( '' === $action ) {
			return;
		}
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['_wpnonce'] ) ), self::NONCE ) ) {
			wc_add_notice( __( 'Your session has expired. Please try again.', 'wootoolkit' ), 'error' );
			self::redirect( self::page_url() ? self::page_url() : home_url( '/' ) );
		}

		$back = self::page_url() ? self::page_url() : home_url( '/' );
		if ( 'update' === $action && isset( $_POST['quantity'] ) && is_array( $_POST['quantity'] ) ) {
			foreach ( wp_unslash( $_POST['quantity'] ) as $key => $quantity ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is cast below.
				QuoteList::set_quantity( sanitize_key( (string) $key ), (int) $quantity );
			}
			wc_add_notice( __( 'Quote list updated.', 'wootoolkit' ) );
		} elseif ( 'remove' === $action && isset( $_GET['item'] ) ) {
			QuoteList::remove( sanitize_key( wp_unslash( $_GET['item'] ) ) );
			wc_add_notice( __( 'Item removed from your quote.', 'wootoolkit' ) );
		} elseif ( 'submit' === $action ) {
			$back = self::process_submit( wp_unslash( $_POST ), $back );
		}
		self::redirect( $back );
	}

	/**
	 * Adds the requested product to the quote list and sets the notice.
	 *
	 * @param array<string, mixed> $request Unslashed request.
	 * @return string|\WP_Error Item key or error.
	 */
	public static function process_add( array $request ) {
		$product_id = absint( $request['wootoolkit_add_to_quote'] ?? 0 );
		$quantity   = isset( $request['quantity'] ) && is_scalar( $request['quantity'] ) ? (int) wc_stock_amount( $request['quantity'] ) : 1;
		$variation  = array();
		foreach ( $request as $name => $value ) {
			if ( is_string( $name ) && 0 === strpos( $name, 'attribute_' ) && is_scalar( $value ) ) {
				$variation[ sanitize_title( $name ) ] = wc_clean( (string) $value );
			}
		}

		$result = QuoteList::add( $product_id, $quantity, absint( $request['variation_id'] ?? 0 ), $variation );
		if ( is_wp_error( $result ) ) {
			wc_add_notice( $result->get_error_message(), 'error' );
			return $result;
		}

		$product = wc_get_product( $product_id );
		/* translators: %s: product name */
		$message = sprintf( __( '&ldquo;%s&rdquo; has been added to your quote.', 'wootoolkit' ), $product ? esc_html( $product->get_name() ) : '' );
		if ( self::page_url() ) {
			$message = sprintf( '<a href="%s" tabindex="1" class="button wc-forward">%s</a> %s', esc_url( self::page_url() ), esc_html__( 'View quote', 'wootoolkit' ), $message );
		}
		wc_add_notice( $message );
		return $result;
	}

	/**
	 * Creates the quote order from the form, and returns where to go next.
	 *
	 * @param array<string, mixed> $post Unslashed form data.
	 * @param string               $back Quote page URL.
	 */
	public static function process_submit( array $post, string $back ): string {
		if ( ! empty( $post[ self::HONEYPOT ] ) ) {
			wc_add_notice( __( 'Your quote request could not be sent.', 'wootoolkit' ), 'error' );
			return $back;
		}
		$data = array();
		foreach ( array_keys( Submission::fields() ) as $name ) {
			$data[ $name ] = isset( $post[ 'quote_' . $name ] ) && is_scalar( $post[ 'quote_' . $name ] ) ? (string) $post[ 'quote_' . $name ] : '';
		}
		$order = Submission::create( $data );
		if ( is_wp_error( $order ) ) {
			wc_add_notice( $order->get_error_message(), 'error' );
			return $back;
		}
		return add_query_arg(
			array(
				'quote-received' => $order->get_id(),
				'key'            => $order->get_order_key(),
			),
			$back
		);
	}

	/**
	 * Renders the quote page (shortcode and block).
	 */
	public static function render_page(): string {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return '<p>' . esc_html__( 'The quote list and request form appear here.', 'wootoolkit' ) . '</p>';
		}

		$received = null;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only, checked against the order key.
		if ( isset( $_GET['quote-received'], $_GET['key'] ) ) {
			$order = wc_get_order( absint( $_GET['quote-received'] ) );
			if ( $order instanceof \WC_Order && hash_equals( $order->get_order_key(), sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) ) {
				$received = $order;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$user   = wp_get_current_user();
		$values = array(
			'first_name' => $user->exists() ? (string) $user->first_name : '',
			'last_name'  => $user->exists() ? (string) $user->last_name : '',
			'email'      => $user->exists() ? (string) $user->user_email : '',
		);

		ob_start();
		if ( function_exists( 'wc_print_notices' ) ) {
			wc_print_notices();
		}
		wc_get_template(
			'quote/quote.php',
			array(
				'received'  => $received,
				'items'     => QuoteList::items(),
				'fields'    => Submission::fields(),
				'values'    => $values,
				'action'    => self::page_url() ? self::page_url() : (string) get_permalink(),
				'nonce'     => wp_create_nonce( self::NONCE ),
				'honeypot'  => self::HONEYPOT,
				'shop_url'  => wc_get_page_permalink( 'shop' ),
			),
			'wootoolkit/',
			WOOTOOLKIT_PATH . 'templates/'
		);
		return (string) ob_get_clean();
	}

	/**
	 * "Add to quote" next to "Add to cart" (inside the add-to-cart form, so quantity and variation are sent).
	 */
	public static function single_button(): void {
		global $product;
		if ( ! $product instanceof \WC_Product || 'yes' !== get_option( self::OPT_ON_PURCHASABLE, 'yes' ) || ! in_array( $product->get_type(), QuoteList::supported_types(), true ) ) {
			return;
		}
		printf(
			' <button type="submit" name="wootoolkit_add_to_quote" value="%d" class="button wootoolkit-add-to-quote">%s</button>',
			(int) $product->get_id(),
			esc_html( self::button_text() )
		);
	}

	/**
	 * Adds "Add to quote" to the Catalog mode button choices.
	 *
	 * @param array<string, string> $types Button types.
	 * @return array<string, string>
	 */
	public static function catalog_button_type( $types ): array {
		$types          = (array) $types;
		$types['quote'] = __( 'Add to quote (Request a quote kit)', 'wootoolkit' );
		return $types;
	}

	/**
	 * The Catalog mode "Add to quote" button.
	 *
	 * @param string      $html    Button HTML.
	 * @param \WC_Product $product Product.
	 * @param string      $context 'loop' or 'single'.
	 * @param string      $type    Button type.
	 */
	public static function catalog_button_html( $html, $product, $context, $type ): string {
		if ( 'quote' !== $type || ! $product instanceof \WC_Product || ! in_array( $product->get_type(), QuoteList::supported_types(), true ) ) {
			return (string) $html;
		}
		if ( 'single' === $context ) {
			return self::standalone_form( $product );
		}
		$url = $product->is_type( 'variable' ) ? $product->get_permalink() : add_query_arg( 'wootoolkit_add_to_quote', $product->get_id(), $product->get_permalink() );
		return sprintf(
			'<a href="%s" class="button wootoolkit-add-to-quote" rel="nofollow">%s</a>',
			esc_url( $url ),
			esc_html( $product->is_type( 'variable' ) ? __( 'Select options', 'wootoolkit' ) : self::button_text() )
		);
	}

	/**
	 * A quote form for products that have no add-to-cart form (catalog mode): options, quantity, button.
	 *
	 * @param \WC_Product $product Product.
	 */
	public static function standalone_form( \WC_Product $product ): string {
		ob_start();
		echo '<form class="wootoolkit-quote-form" method="post" action="' . esc_url( $product->get_permalink() ) . '">';
		if ( $product instanceof \WC_Product_Variable ) {
			echo '<table class="variations" role="presentation"><tbody>';
			foreach ( $product->get_variation_attributes() as $attribute => $options ) {
				echo '<tr><th class="label"><label for="' . esc_attr( sanitize_title( $attribute ) ) . '">' . esc_html( wc_attribute_label( $attribute, $product ) ) . '</label></th><td class="value">';
				wc_dropdown_variation_attribute_options(
					array(
						'options'   => $options,
						'attribute' => $attribute,
						'product'   => $product,
					)
				);
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		woocommerce_quantity_input(
			array(
				'min_value'   => 1,
				'input_value' => 1,
			),
			$product
		);
		printf(
			'<button type="submit" name="wootoolkit_add_to_quote" value="%d" class="button alt wootoolkit-add-to-quote">%s</button></form>',
			(int) $product->get_id(),
			esc_html( self::button_text() )
		);
		return (string) ob_get_clean();
	}

	/**
	 * Redirects and stops (filterable for tests).
	 *
	 * @param string $url Target.
	 */
	private static function redirect( string $url ): void {
		/**
		 * Whether to redirect after a quote request (false in tests).
		 *
		 * @param bool   $redirect Redirect.
		 * @param string $url      Target.
		 */
		if ( ! apply_filters( 'wootoolkit_quote_redirect', true, $url ) ) {
			return;
		}
		wp_safe_redirect( $url );
		exit;
	}
}
