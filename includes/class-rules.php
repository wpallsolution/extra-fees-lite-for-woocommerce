<?php
/**
 * Fee rule storage and sanitising.
 *
 * @package Extra_Fees_Lite
 */

defined( 'ABSPATH' ) || exit;

class EXFEE_Rules {

	const OPTION = 'exfee_rules';

	public static function defaults() {
		return array(
			'id'           => 0,
			'enabled'      => 1,
			'label'        => '',
			'type'         => 'fixed',
			'amount'       => 0,
			'max_fee'      => '',
			'taxable'      => 0,
			'tax_class'    => '',
			'min_subtotal' => '',
			'max_subtotal' => '',
			'products'     => array(),
			'categories'   => array(),
			'gateways'     => array(),
		);
	}

	/** @return array<int,array> Rules keyed by id. */
	public static function all() {
		$rules = get_option( self::OPTION, array() );
		if ( ! is_array( $rules ) ) {
			return array();
		}
		$out = array();
		foreach ( $rules as $id => $rule ) {
			if ( is_array( $rule ) ) {
				$out[ (int) $id ] = wp_parse_args( $rule, self::defaults() );
			}
		}
		return $out;
	}

	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ (int) $id ] ) ? $all[ (int) $id ] : null;
	}

	const WPML_CONTEXT = 'extra-fees-lite-for-woocommerce';

	/** Fee label as a WPML String Translation string, one per rule. */
	private static function register_label( $id, $label ) {
		if ( has_action( 'wpml_register_single_string' ) || has_filter( 'wpml_translate_single_string' ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
			do_action( 'wpml_register_single_string', self::WPML_CONTEXT, 'fee_label_' . (int) $id, $label ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
		}
	}

	/** Label translated into the current (or given) language; the original when WPML is absent. */
	public static function translated_label( $rule, $lang = '' ) {
		$label = $rule['label'];
		if ( ! has_filter( 'wpml_translate_single_string' ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
			return $label;
		}

		$name       = 'fee_label_' . (int) $rule['id'];
		$translated = apply_filters( 'wpml_translate_single_string', $label, self::WPML_CONTEXT, $name, $lang ? $lang : null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
		if ( $translated !== $label ) {
			return $translated;
		}

		// Fall back to the stored translation when String Translation has not compiled it yet.
		$target = $lang ? $lang : apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
		if ( $target && $target !== apply_filters( 'wpml_default_language', null ) && function_exists( 'icl_get_string_id' ) && function_exists( 'icl_get_string_by_id' ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Hook provided by WPML.
			$string_id = icl_get_string_id( $label, self::WPML_CONTEXT, $name );
			$stored    = $string_id ? icl_get_string_by_id( $string_id, $target ) : '';
			if ( is_string( $stored ) && '' !== $stored ) {
				return $stored;
			}
		}
		return $label;
	}

	public static function save( array $rule ) {
		$all = self::all();
		$id  = (int) $rule['id'];
		if ( $id <= 0 || ! isset( $all[ $id ] ) ) {
			$id           = $all ? max( array_keys( $all ) ) + 1 : 1;
			$rule['id']   = $id;
		}
		$all[ $id ] = $rule;
		update_option( self::OPTION, $all, false );
		self::register_label( $id, $rule['label'] );
		return $id;
	}

	public static function delete( $id ) {
		$all = self::all();
		unset( $all[ (int) $id ] );
		update_option( self::OPTION, $all, false );
	}

	public static function toggle( $id ) {
		$all = self::all();
		if ( isset( $all[ (int) $id ] ) ) {
			$all[ (int) $id ]['enabled'] = empty( $all[ (int) $id ]['enabled'] ) ? 1 : 0;
			update_option( self::OPTION, $all, false );
		}
	}

	/** Money field: empty string or a non-negative decimal. */
	private static function money( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		$value = (float) wc_format_decimal( $value );
		return $value < 0 ? 0 : $value;
	}

	/**
	 * @param array $input Raw (slashed) form data.
	 * @return array|WP_Error
	 */
	public static function sanitize( array $input ) {
		$d     = self::defaults();
		$input = wp_unslash( $input );

		$label = isset( $input['label'] ) ? sanitize_text_field( $input['label'] ) : '';
		if ( '' === $label ) {
			return new WP_Error( 'label', __( 'Please enter a fee label.', 'extra-fees-lite-for-woocommerce' ) );
		}

		$type   = isset( $input['type'] ) && 'percentage' === $input['type'] ? 'percentage' : 'fixed';
		$amount = self::money( isset( $input['amount'] ) ? $input['amount'] : '' );
		if ( '' === $amount || $amount <= 0 ) {
			return new WP_Error( 'amount', __( 'Please enter a fee amount greater than zero.', 'extra-fees-lite-for-woocommerce' ) );
		}
		if ( 'percentage' === $type && $amount > 100 ) {
			return new WP_Error( 'amount', __( 'A percentage fee cannot be more than 100.', 'extra-fees-lite-for-woocommerce' ) );
		}

		$min = self::money( isset( $input['min_subtotal'] ) ? $input['min_subtotal'] : '' );
		$max = self::money( isset( $input['max_subtotal'] ) ? $input['max_subtotal'] : '' );
		if ( '' !== $min && '' !== $max && $max < $min ) {
			return new WP_Error( 'subtotal', __( 'Maximum subtotal must not be lower than the minimum.', 'extra-fees-lite-for-woocommerce' ) );
		}

		$tax_classes = array_merge( array( '' ), WC_Tax::get_tax_class_slugs() );
		$tax_class   = isset( $input['tax_class'] ) ? sanitize_title( $input['tax_class'] ) : '';

		$known_gateways = array_keys( WC()->payment_gateways()->payment_gateways() );
		$gateways       = isset( $input['gateways'] ) && is_array( $input['gateways'] ) ? array_map( 'sanitize_key', $input['gateways'] ) : array();

		return array(
			'id'           => isset( $input['id'] ) ? absint( $input['id'] ) : 0,
			'enabled'      => empty( $input['enabled'] ) ? 0 : 1,
			'label'        => $label,
			'type'         => $type,
			'amount'       => $amount,
			'max_fee'      => 'percentage' === $type ? self::money( isset( $input['max_fee'] ) ? $input['max_fee'] : '' ) : '',
			'taxable'      => empty( $input['taxable'] ) ? 0 : 1,
			'tax_class'    => in_array( $tax_class, $tax_classes, true ) ? $tax_class : $d['tax_class'],
			'min_subtotal' => $min,
			'max_subtotal' => $max,
			'products'     => isset( $input['products'] ) && is_array( $input['products'] ) ? array_values( array_filter( array_map( 'absint', $input['products'] ) ) ) : array(),
			'categories'   => isset( $input['categories'] ) && is_array( $input['categories'] ) ? array_values( array_filter( array_map( 'absint', $input['categories'] ) ) ) : array(),
			'gateways'     => array_values( array_intersect( $gateways, $known_gateways ) ),
		);
	}
}
