<?php

/**
 * Plugin Name: HeadlessWC: Ultimate eCommerce Decoupler
 * Text Domain: headless - wc
 * Domain Path: /languages
 * Description: Custom WC endpoints for headless checkout
 * Version: 1.1.7
 * Author: Dawid Wiewiórski
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires PHP: 7.4
 */

// Exit if accessed directly.
if (! defined('ABSPATH')) {
    exit;
}

define('HEADLESSWC_PATH', plugin_dir_path(__FILE__));
define('HEADLESSWC_BASENAME', plugin_basename(__FILE__));
require_once HEADLESSWC_PATH . 'vendor/autoload.php';
require_once HEADLESSWC_PATH . 'includes/check-plugin-requirements.php';
require_once HEADLESSWC_PATH . 'includes/redirect_after_order.php';
require_once HEADLESSWC_PATH . 'api/create-cart.php';
require_once HEADLESSWC_PATH . 'api/create-order.php';
require_once HEADLESSWC_PATH . 'api/get-order-details.php';
require_once HEADLESSWC_PATH . 'api/get-all-products.php';
require_once HEADLESSWC_PATH . 'api/get-single-product.php';
require_once HEADLESSWC_PATH . 'classes/product.php';
require_once HEADLESSWC_PATH . 'classes/product-detailed.php';
require_once HEADLESSWC_PATH . 'classes/cart-product.php';
require_once HEADLESSWC_PATH . 'utilities/get-attributes-data.php';
require_once HEADLESSWC_PATH . 'utilities/get-attributes.php';
require_once HEADLESSWC_PATH . 'utilities/get-gallery-images.php';
require_once HEADLESSWC_PATH . 'utilities/get-image-sizes.php';
require_once HEADLESSWC_PATH . 'utilities/get-meta-data.php';
require_once HEADLESSWC_PATH . 'utilities/get-regular-price.php';
require_once HEADLESSWC_PATH . 'utilities/get-sale-price.php';
require_once HEADLESSWC_PATH . 'utilities/nvl.php';


add_action(
    'rest_api_init',
    function () {
        if (! class_exists('WooCommerce') || ! WC()->cart) {
            WC()->initialize_session();
            WC()->initialize_cart();
        }
        register_rest_route(
            'headless-wc/v1',
            '/cart',
            array(
                'methods' => 'POST',
                'callback' => 'headlesswc_handle_cart_request',
                'permission_callback' => '__return_true',
            )
        );
        register_rest_route(
            'headless-wc/v1',
            '/order',
            array(
                'methods' => 'POST',
                'callback' => 'headlesswc_handle_order_request',
                'permission_callback' => '__return_true',

            )
        );
        register_rest_route(
            'headless-wc/v1',
            '/order/(?P<order_id>\d+)',
            array(
                'methods' => 'GET',
                'callback' => 'headlesswc_handle_order_details_request',
                'permission_callback' => '__return_true',
                'args' => array(
                    'order_id' => array(
                        'validate_callback' => function ($param, $request, $key) {
                            return is_numeric($param);
                        }
                    ),
                    'key' => array(
                        'required' => true,
                        'validate_callback' => function ($param, $request, $key) {
                            return !empty($param);
                        }
                    ),
                ),
            )
        );
        register_rest_route(
            'headless-wc/v1',
            '/products',
            array(
                'methods' => 'GET',
                'callback' => 'headlesswc_handle_products_request',
                'permission_callback' => '__return_true',

            )
        );
        register_rest_route(
            'headless-wc/v1',
            '/products/(?P<slug>[a-zA-Z0-9-]+)',
            array(
                'methods' => 'GET',
                'callback' => 'headlesswc_handle_product_request',
                'permission_callback' => '__return_true',
            )
        );
    }
);

add_action('plugins_loaded', 'headlesswc_check_plugin_requirements', 0);
add_action('template_redirect', 'headlesswc_redirect_after_order', 20);
