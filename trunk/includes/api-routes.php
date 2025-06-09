<?php
if (! defined('ABSPATH')) {
    exit;
}

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