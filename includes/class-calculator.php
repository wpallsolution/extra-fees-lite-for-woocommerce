<?php
/**
 * Applies fee rules to the cart. Runs for classic checkout and the Store API (block checkout).
 *
 * @package Extra_Fees_Lite
 */

defined( 'ABSPATH' ) || exit;

class EXFEE_Calculator {

	public static function init() {
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'apply' ), 20 );
	}

	/**
	 * @param WC_Cart $cart Cart being calculated.
	 */
	public static function apply( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() && ! defined( 'REST_REQUEST' ) ) {
			return;
		}

		$rules = array_filter(
			EXFEE_Rules::all(),
			static function ( $rule ) {
				return ! empty( $rule['enabled'] );
			}
		);
		if ( ! $rules ) {
			return;
		}

		$subtotal = (float) $cart->get_subtotal();
		$used     = array();

		foreach ( $rules as $rule ) {
			if ( ! self::matches( $rule, $cart, $subtotal ) ) {
				continue;
			}

			$fee = self::amount( $rule, $subtotal );
			if ( $fee <= 0 ) {
				continue;
			}

			$label = self::unique_label( EXFEE_Rules::translated_label( $rule ), $used );
			$cart->add_fee( $label, $fee, ! empty( $rule['taxable'] ), (string) $rule['tax_class'] );
		}
	}

	/**
	 * Convert an amount typed in the store's base currency into the currency the customer is
	 * shopping in. Rules are always stored in the base currency.
	 *
	 * Use the `exfee_convert_amount` filter to connect any currency switcher. WPML's WCML is
	 * supported through its documented `wcml_raw_price_amount` filter, and CURCY through wmc_get_price().
	 *
	 * @param float $amount Amount in the base currency.
	 * @return float
	 */
	public static function convert( $amount ) {
		$amount    = (float) $amount;
		$converted = (float) apply_filters( 'exfee_convert_amount', $amount );

		if ( $converted === $amount && has_filter( 'wcml_raw_price_amount' ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WooCommerce Multilingual.
			$converted = (float) apply_filters( 'wcml_raw_price_amount', $amount ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WooCommerce Multilingual.
		}

		// CURCY (Woo Multi Currency) leaves fee amounts alone, so convert them with its own function.
		if ( $converted === $amount && function_exists( 'wmc_get_price' ) ) {
			$converted = (float) wmc_get_price( $amount );
		}

		return $converted;
	}

	public static function amount( $rule, $subtotal ) {
		if ( 'percentage' === $rule['type'] ) {
			$fee = $subtotal * (float) $rule['amount'] / 100;
			if ( '' !== $rule['max_fee'] && (float) $rule['max_fee'] > 0 ) {
				$fee = min( $fee, self::convert( $rule['max_fee'] ) );
			}
		} else {
			$fee = self::convert( $rule['amount'] );
		}
		return round( $fee, wc_get_price_decimals() );
	}

	public static function matches( $rule, $cart, $subtotal ) {
		if ( '' !== $rule['min_subtotal'] && $subtotal < self::convert( $rule['min_subtotal'] ) ) {
			return false;
		}
		if ( '' !== $rule['max_subtotal'] && $subtotal > self::convert( $rule['max_subtotal'] ) ) {
			return false;
		}

		if ( ! empty( $rule['products'] ) && ! self::cart_has_product( $cart, $rule['products'] ) ) {
			return false;
		}
		if ( ! empty( $rule['categories'] ) && ! self::cart_has_category( $cart, $rule['categories'] ) ) {
			return false;
		}

		if ( ! empty( $rule['gateways'] ) ) {
			$chosen = WC()->session ? (string) WC()->session->get( 'chosen_payment_method' ) : '';
			if ( '' === $chosen || ! in_array( $chosen, $rule['gateways'], true ) ) {
				return false;
			}
		}

		return true;
	}

	private static function cart_has_product( $cart, array $ids ) {
		foreach ( $cart->get_cart() as $item ) {
			if ( in_array( (int) $item['product_id'], $ids, true ) || ( ! empty( $item['variation_id'] ) && in_array( (int) $item['variation_id'], $ids, true ) ) ) {
				return true;
			}
		}
		return false;
	}

	private static function cart_has_category( $cart, array $term_ids ) {
		foreach ( $cart->get_cart() as $item ) {
			$cats = wc_get_product_term_ids( (int) $item['product_id'], 'product_cat' );
			// Match a selected category or any of its parents.
			foreach ( $cats as $cat ) {
				$chain = array_merge( array( $cat ), get_ancestors( $cat, 'product_cat', 'taxonomy' ) );
				if ( array_intersect( $chain, $term_ids ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/** WooCommerce ignores a fee whose name already exists, so keep labels unique. */
	private static function unique_label( $label, array &$used ) {
		$candidate = $label;
		$n         = 2;
		while ( in_array( sanitize_title( $candidate ), $used, true ) ) {
			$candidate = $label . ' (' . $n++ . ')';
		}
		$used[] = sanitize_title( $candidate );
		return $candidate;
	}
}
