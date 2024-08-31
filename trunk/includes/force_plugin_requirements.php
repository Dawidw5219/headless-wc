<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function headlesswc_force_plugin_requirements() {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( ! class_exists( 'WooCommerce' ) ) {
		if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			add_action(
				'admin_notices',
				function () {
					echo '<div class="error"><p>' . esc_html__( 'HeadlessWC plugin cannot operate without WooCommerce being enabled. Please install and activate the WooCommerce plugin', 'headless-wc' ) . '</p></div>';
				}
			);
		}
	}
}
