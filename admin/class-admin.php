<?php
/**
 * Admin screens: rule list and rule editor.
 *
 * @package Extra_Fees_Lite
 */

defined( 'ABSPATH' ) || exit;

class EXFEE_Admin {

	const SLUG = 'extra-fees-lite';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_exfee_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_exfee_delete', array( __CLASS__, 'delete' ) );
		add_action( 'admin_post_exfee_toggle', array( __CLASS__, 'toggle' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( EXFEE_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		$hook = add_submenu_page(
			'woocommerce',
			__( 'Extra Fees', 'extra-fees-lite-for-woocommerce' ),
			__( 'Extra Fees', 'extra-fees-lite-for-woocommerce' ),
			'manage_woocommerce',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
		add_action(
			'load-' . $hook,
			static function () {
				wp_enqueue_script( 'wc-enhanced-select' );
				wp_enqueue_style( 'woocommerce_admin_styles' );
			}
		);
	}

	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Fees', 'extra-fees-lite-for-woocommerce' ) . '</a>' );
		return $links;
	}

	private static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	private static function guard( $nonce_action = '' ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'extra-fees-lite-for-woocommerce' ), '', array( 'response' => 403 ) );
		}
		if ( $nonce_action ) {
			check_admin_referer( $nonce_action );
		}
	}

	/* ---------------------------------------------------------------- views */

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'extra-fees-lite-for-woocommerce' ), '', array( 'response' => 403 ) );
		}

		echo '<div class="wrap"><h1 class="wp-heading-inline">' . esc_html__( 'Extra Fees', 'extra-fees-lite-for-woocommerce' ) . '</h1>';

		$edit = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( null !== $edit ) {
			echo '<hr class="wp-header-end">';
			self::notices();
			self::render_form( $edit ? EXFEE_Rules::get( $edit ) : null );
		} else {
			echo ' <a href="' . esc_url( self::url( array( 'edit' => 0 ) ) ) . '" class="page-title-action">' . esc_html__( 'Add fee', 'extra-fees-lite-for-woocommerce' ) . '</a><hr class="wp-header-end">';
			self::notices();
			self::render_list();
		}
		echo '</div>';
	}

	private static function notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['saved'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Done.', 'extra-fees-lite-for-woocommerce' ) . '</p></div>';
		}
		if ( isset( $_GET['error'] ) ) {
			$message = get_transient( 'exfee_notice_' . get_current_user_id() );
			delete_transient( 'exfee_notice_' . get_current_user_id() );
			if ( $message ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
			}
		}
		// phpcs:enable
	}

	private static function summary( $rule ) {
		$parts = array();
		if ( '' !== $rule['min_subtotal'] ) {
			/* translators: %s: amount */
			$parts[] = sprintf( __( 'Subtotal from %s', 'extra-fees-lite-for-woocommerce' ), wp_strip_all_tags( wc_price( $rule['min_subtotal'] ) ) );
		}
		if ( '' !== $rule['max_subtotal'] ) {
			/* translators: %s: amount */
			$parts[] = sprintf( __( 'Subtotal up to %s', 'extra-fees-lite-for-woocommerce' ), wp_strip_all_tags( wc_price( $rule['max_subtotal'] ) ) );
		}
		if ( $rule['products'] ) {
			/* translators: %d: number of products */
			$parts[] = sprintf( _n( '%d product', '%d products', count( $rule['products'] ), 'extra-fees-lite-for-woocommerce' ), count( $rule['products'] ) );
		}
		if ( $rule['categories'] ) {
			/* translators: %d: number of categories */
			$parts[] = sprintf( _n( '%d category', '%d categories', count( $rule['categories'] ), 'extra-fees-lite-for-woocommerce' ), count( $rule['categories'] ) );
		}
		if ( $rule['gateways'] ) {
			$parts[] = implode( ', ', array_map( 'esc_html', $rule['gateways'] ) );
		}
		return $parts ? implode( ' &middot; ', $parts ) : esc_html__( 'Always', 'extra-fees-lite-for-woocommerce' );
	}

	private static function render_list() {
		$rules = EXFEE_Rules::all();

		echo '<table class="widefat striped" style="margin-top:1em"><thead><tr>';
		foreach ( array( __( 'Label', 'extra-fees-lite-for-woocommerce' ), __( 'Fee', 'extra-fees-lite-for-woocommerce' ), __( 'Applies when', 'extra-fees-lite-for-woocommerce' ), __( 'Status', 'extra-fees-lite-for-woocommerce' ), '' ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( ! $rules ) {
			echo '<tr><td colspan="5">' . esc_html__( 'No fees yet. Click "Add fee" to create one.', 'extra-fees-lite-for-woocommerce' ) . '</td></tr>';
		}

		foreach ( $rules as $id => $rule ) {
			$fee = 'percentage' === $rule['type'] ? wc_format_localized_decimal( $rule['amount'] ) . '%' : wp_strip_all_tags( wc_price( $rule['amount'] ) );

			$toggle = wp_nonce_url( admin_url( 'admin-post.php?action=exfee_toggle&id=' . $id ), 'exfee_toggle_' . $id );
			$delete = wp_nonce_url( admin_url( 'admin-post.php?action=exfee_delete&id=' . $id ), 'exfee_delete_' . $id );

			echo '<tr><td><strong><a href="' . esc_url( self::url( array( 'edit' => $id ) ) ) . '">' . esc_html( $rule['label'] ) . '</a></strong></td>';
			echo '<td>' . esc_html( $fee ) . '</td>';
			echo '<td>' . wp_kses_post( self::summary( $rule ) ) . '</td>';
			echo '<td><a href="' . esc_url( $toggle ) . '">' . ( $rule['enabled'] ? '<span style="color:#1e7e34">' . esc_html__( 'Enabled', 'extra-fees-lite-for-woocommerce' ) . '</span>' : esc_html__( 'Disabled', 'extra-fees-lite-for-woocommerce' ) ) . '</a></td>';
			echo '<td><a href="' . esc_url( self::url( array( 'edit' => $id ) ) ) . '">' . esc_html__( 'Edit', 'extra-fees-lite-for-woocommerce' ) . '</a> | <a href="' . esc_url( $delete ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this fee?', 'extra-fees-lite-for-woocommerce' ) ) . '\')">' . esc_html__( 'Delete', 'extra-fees-lite-for-woocommerce' ) . '</a></td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function render_form( $rule ) {
		$r = $rule ? $rule : EXFEE_Rules::defaults();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="exfee_save"><input type="hidden" name="rule[id]" value="' . esc_attr( $r['id'] ) . '">';
		wp_nonce_field( 'exfee_save' );
		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row">' . esc_html__( 'Enabled', 'extra-fees-lite-for-woocommerce' ) . '</th><td><label><input type="checkbox" name="rule[enabled]" value="1"' . checked( ! empty( $r['enabled'] ), true, false ) . '> ' . esc_html__( 'Apply this fee', 'extra-fees-lite-for-woocommerce' ) . '</label></td></tr>';

		echo '<tr><th scope="row"><label for="exfee-label">' . esc_html__( 'Fee label', 'extra-fees-lite-for-woocommerce' ) . '</label></th><td><input type="text" id="exfee-label" name="rule[label]" value="' . esc_attr( $r['label'] ) . '" class="regular-text" required><p class="description">' . esc_html__( 'Shown to the customer in the cart and checkout totals, e.g. "Handling fee".', 'extra-fees-lite-for-woocommerce' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Fee type', 'extra-fees-lite-for-woocommerce' ) . '</th><td><select name="rule[type]"><option value="fixed"' . selected( $r['type'], 'fixed', false ) . '>' . esc_html__( 'Fixed amount', 'extra-fees-lite-for-woocommerce' ) . '</option><option value="percentage"' . selected( $r['type'], 'percentage', false ) . '>' . esc_html__( 'Percentage of cart subtotal', 'extra-fees-lite-for-woocommerce' ) . '</option></select></td></tr>';

		echo '<tr><th scope="row"><label for="exfee-amount">' . esc_html__( 'Amount', 'extra-fees-lite-for-woocommerce' ) . '</label></th><td><input type="text" id="exfee-amount" name="rule[amount]" value="' . esc_attr( wc_format_localized_decimal( $r['amount'] ) ) . '" class="wc_input_price short" required><p class="description">' . esc_html__( 'A currency amount for fixed fees, or a number such as 5 for 5%. The percentage is taken from the cart subtotal before tax and discounts.', 'extra-fees-lite-for-woocommerce' ) . '</p></td></tr>';

		echo '<tr><th scope="row"><label for="exfee-max">' . esc_html__( 'Maximum fee', 'extra-fees-lite-for-woocommerce' ) . '</label></th><td><input type="text" id="exfee-max" name="rule[max_fee]" value="' . esc_attr( '' === $r['max_fee'] ? '' : wc_format_localized_decimal( $r['max_fee'] ) ) . '" class="wc_input_price short"><p class="description">' . esc_html__( 'Optional cap for percentage fees. Leave empty for no cap.', 'extra-fees-lite-for-woocommerce' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Tax', 'extra-fees-lite-for-woocommerce' ) . '</th><td><label><input type="checkbox" name="rule[taxable]" value="1"' . checked( ! empty( $r['taxable'] ), true, false ) . '> ' . esc_html__( 'Fee is taxable', 'extra-fees-lite-for-woocommerce' ) . '</label> &nbsp; <select name="rule[tax_class]"><option value="">' . esc_html__( 'Standard', 'extra-fees-lite-for-woocommerce' ) . '</option>';
		foreach ( WC_Tax::get_tax_class_slugs() as $slug ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $r['tax_class'], $slug, false ) . '>' . esc_html( $slug ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Currency', 'extra-fees-lite-for-woocommerce' ) . '</th><td><p class="description" style="margin-top:0">' . esc_html(
			sprintf(
				/* translators: %s: base currency code */
				__( 'Amounts on this screen are in your store base currency (%s). They are converted automatically when a multi-currency plugin is active (CURCY and WPML WooCommerce Multilingual are supported; other plugins can use the exfee_convert_amount filter).', 'extra-fees-lite-for-woocommerce' ),
				get_option( 'woocommerce_currency' )
			)
		) . '</p></td></tr>';

		echo '</table><h2>' . esc_html__( 'Conditions', 'extra-fees-lite-for-woocommerce' ) . '</h2><p>' . esc_html__( 'The fee applies only when every condition you fill in is met. Leave a condition empty to ignore it.', 'extra-fees-lite-for-woocommerce' ) . '</p><table class="form-table" role="presentation">';

		echo '<tr><th scope="row">' . esc_html__( 'Cart subtotal', 'extra-fees-lite-for-woocommerce' ) . '</th><td>' . esc_html__( 'From', 'extra-fees-lite-for-woocommerce' ) . ' <input type="text" name="rule[min_subtotal]" value="' . esc_attr( '' === $r['min_subtotal'] ? '' : wc_format_localized_decimal( $r['min_subtotal'] ) ) . '" class="wc_input_price short"> ' . esc_html__( 'up to', 'extra-fees-lite-for-woocommerce' ) . ' <input type="text" name="rule[max_subtotal]" value="' . esc_attr( '' === $r['max_subtotal'] ? '' : wc_format_localized_decimal( $r['max_subtotal'] ) ) . '" class="wc_input_price short"></td></tr>';

		echo '<tr><th scope="row"><label for="exfee-products">' . esc_html__( 'Cart contains products', 'extra-fees-lite-for-woocommerce' ) . '</label></th><td><select id="exfee-products" class="wc-product-search" name="rule[products][]" multiple style="width:50%" data-placeholder="' . esc_attr__( 'Search for a product...', 'extra-fees-lite-for-woocommerce' ) . '" data-action="woocommerce_json_search_products_and_variations">';
		foreach ( $r['products'] as $pid ) {
			$product = wc_get_product( $pid );
			if ( $product ) {
				echo '<option value="' . esc_attr( $pid ) . '" selected>' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
			}
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row"><label for="exfee-cats">' . esc_html__( 'Cart contains categories', 'extra-fees-lite-for-woocommerce' ) . '</label></th><td><select id="exfee-cats" class="wc-enhanced-select" name="rule[categories][]" multiple style="width:50%" data-placeholder="' . esc_attr__( 'Choose categories...', 'extra-fees-lite-for-woocommerce' ) . '">';
		$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
			echo '<option value="' . esc_attr( $term->term_id ) . '"' . selected( in_array( $term->term_id, $r['categories'], true ), true, false ) . '>' . esc_html( $term->name ) . '</option>';
		}
		echo '</select></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Payment method', 'extra-fees-lite-for-woocommerce' ) . '</th><td>';
		foreach ( WC()->payment_gateways()->payment_gateways() as $gw_id => $gateway ) {
			echo '<label style="display:block"><input type="checkbox" name="rule[gateways][]" value="' . esc_attr( $gw_id ) . '"' . checked( in_array( $gw_id, $r['gateways'], true ), true, false ) . '> ' . esc_html( wp_strip_all_tags( $gateway->get_method_title() ) ) . '</label>';
		}
		echo '<p class="description">' . esc_html__( 'Fee applies only when the customer picks one of the ticked payment methods.', 'extra-fees-lite-for-woocommerce' ) . '</p></td></tr>';

		echo '</table>';
		submit_button( $rule ? __( 'Update fee', 'extra-fees-lite-for-woocommerce' ) : __( 'Add fee', 'extra-fees-lite-for-woocommerce' ) );
		echo ' <a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Back to list', 'extra-fees-lite-for-woocommerce' ) . '</a></form>';
	}

	/* -------------------------------------------------------------- actions */

	public static function save() {
		self::guard();
		check_admin_referer( 'exfee_save' );

		$input = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised in EXFEE_Rules::sanitize().
		$rule  = EXFEE_Rules::sanitize( wp_slash( $input ) );

		if ( is_wp_error( $rule ) ) {
			set_transient( 'exfee_notice_' . get_current_user_id(), $rule->get_error_message(), MINUTE_IN_SECONDS );
			wp_safe_redirect( self::url( array( 'edit' => isset( $input['id'] ) ? absint( $input['id'] ) : 0, 'error' => 1 ) ) );
			exit;
		}

		EXFEE_Rules::save( $rule );
		wp_safe_redirect( self::url( array( 'saved' => 1 ) ) );
		exit;
	}

	public static function delete() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is bound to this id and verified in guard().
		self::guard( 'exfee_delete_' . $id );
		EXFEE_Rules::delete( $id );
		wp_safe_redirect( self::url( array( 'saved' => 1 ) ) );
		exit;
	}

	public static function toggle() {
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is bound to this id and verified in guard().
		self::guard( 'exfee_toggle_' . $id );
		EXFEE_Rules::toggle( $id );
		wp_safe_redirect( self::url( array( 'saved' => 1 ) ) );
		exit;
	}
}
