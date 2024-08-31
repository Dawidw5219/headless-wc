<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function headlesswc_check_plugin_requirements() {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
    $woocommerce_active = false;
    foreach ( apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) as $plugin ) {
        if ( strpos( $plugin, 'woocommerce.php' ) !== false ) {
            $woocommerce_active = true;
            break;
        }
    }

    if ( ! $woocommerce_active ) {
        deactivate_plugins( HEADLESSWC_BASENAME );
        add_action(
            'admin_notices',
            function () {
				echo '<div class="error"><p>' . wp_kses_post( __( '<b>HeadlessWC</b> requires <b>WooCommerce</b> to be installed and activated.', 'headless-wc' ) ) . '</p></div>';
            }
        );
    }
}

add_action( 'plugins_loaded', 'headlesswc_check_plugin_requirements', 20 );
