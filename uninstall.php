<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package Extra_Fees_Lite
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'exfee_rules' );
