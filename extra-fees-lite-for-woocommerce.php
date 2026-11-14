<?php
/**
 * Plugin Name:       Extra Fees Lite for WooCommerce
 * Description:       Add fixed or percentage fees to the cart with conditions for cart subtotal, products, categories and payment methods. Works with classic and block checkout.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            hplugin
 * Author URI:        https://profiles.wordpress.org/hplugin/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       extra-fees-lite-for-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 8.0
 *
 * @package Extra_Fees_Lite
 */

defined( 'ABSPATH' ) || exit;

define( 'EXFEE_VERSION', '1.0.0' );
define( 'EXFEE_MIN_WC', '8.0' );
define( 'EXFEE_FILE', __FILE__ );
define( 'EXFEE_PATH', plugin_dir_path( __FILE__ ) );

require_once EXFEE_PATH . 'includes/class-rules.php';
require_once EXFEE_PATH . 'includes/class-calculator.php';
require_once EXFEE_PATH . 'admin/class-admin.php';

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', EXFEE_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', EXFEE_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) || version_compare( WC_VERSION, EXFEE_MIN_WC, '<' ) ) {
			add_action(
				'admin_notices',
				static function () {
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html(
							sprintf(
								/* translators: %s: minimum WooCommerce version */
								__( 'Extra Fees Lite requires WooCommerce %s or newer.', 'extra-fees-lite-for-woocommerce' ),
								EXFEE_MIN_WC
							)
						)
					);
				}
			);
			return;
		}

		EXFEE_Calculator::init();

		if ( is_admin() ) {
			EXFEE_Admin::init();
		}
	}
);
