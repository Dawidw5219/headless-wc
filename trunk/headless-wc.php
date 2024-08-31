<?php
/**
 * Plugin Name: HeadlessWC: Ultimate eCommerce Decoupler
 * Text Domain: headless-wc
 * Domain Path: /languages
 * Description: Custom WC endpoints for headless checkout
 * Version: 1.1.0
 * Author: Dawid Wiewi!órski
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'HEADLESSWC_PATH', plugin_dir_path( __FILE__ ) );
define( 'HEADLESSWC_URL', plugin_dir_url( __FILE__ ) );
require_once HEADLESSWC_PATH . 'vendor/autoload.php';
require_once HEADLESSWC_PATH . 'includes/force_plugin_requirements.php';
require_once HEADLESSWC_PATH . 'includes/redirect_after_order.php';
require_once HEADLESSWC_PATH . 'api/create-cart.php';
require_once HEADLESSWC_PATH . 'api/create-order.php';
require_once HEADLESSWC_PATH . 'api/get-all-products.php';
require_once HEADLESSWC_PATH . 'api/get-single-product.php';
require_once HEADLESSWC_PATH . 'utilities/get-image-sizes.php';
require_once HEADLESSWC_PATH . 'classes/product.php';
require_once HEADLESSWC_PATH . 'classes/simple-product.php';
require_once HEADLESSWC_PATH . 'classes/variable-product.php';

add_action(
    'rest_api_init',
    function () {
        if ( ! class_exists( 'WooCommerce' ) || ! WC()->cart ) {
            WC()->initialize_session();
            WC()->initialize_cart();
        }
        register_rest_route(
            'headless-wc/v1',
            '/cart',
            array(
                'methods' => 'POST',
                'callback' => 'headlesswc_handle_cart_request',
            )
        );
        register_rest_route(
            'headless-wc/v1',
            '/order',
            array(
                'methods' => 'POST',
                'callback' => 'headlesswc_handle_order_request',
            )
        );
        register_rest_route(
            'headless-wc/v1',
            '/products',
            array(
                'methods' => 'GET',
                'callback' => 'headlesswc_handle_products_request',
            )
        );
        register_rest_route(
            'headless-wc/v1',
            '/products/(?P<slug>[a-zA-Z0-9-]+)',
            array(
                'methods' => 'GET',
                'callback' => 'headlesswc_handle_product_request',
            )
        );
    }
);

add_action(
    'plugins_loaded', 'headlesswc_force_plugin_requirements
', 0
);
add_action(
    'template_redirect', 'headlesswc_redirect_after_order
'
);
