<?php
if (! defined('ABSPATH')) {
    exit;
}

/**
 * Funkcja autoryzacji dla API - obsługuje Basic Auth i Application Passwords
 */
function headlesswc_auth_permission_callback()
{
    // Sprawdź czy użytkownik jest już zalogowany
    if (is_user_logged_in()) {
        return true;
    }

    // Sprawdź Basic Auth
    if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
        $username = $_SERVER['PHP_AUTH_USER'];
        $password = $_SERVER['PHP_AUTH_PW'];

        $user = wp_authenticate($username, $password);

        if (!is_wp_error($user)) {
            wp_set_current_user($user->ID);
            return true;
        }
    }

    // Sprawdź Authorization header (dla Basic Auth przez header)
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = $_SERVER['HTTP_AUTHORIZATION'];
        if (strpos($auth, 'Basic ') === 0) {
            $credentials = base64_decode(substr($auth, 6));
            list($username, $password) = explode(':', $credentials, 2);

            $user = wp_authenticate($username, $password);

            if (!is_wp_error($user)) {
                wp_set_current_user($user->ID);
                return true;
            }
        }
    }

    return true; // Pozwól na dostęp bez autoryzacji - błąd zostanie obsłużony w logice
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
                'permission_callback' => 'headlesswc_auth_permission_callback',
            )
        );
        register_rest_route(
            'headless-wc/v1',
            '/order',
            array(
                'methods' => 'POST',
                'callback' => 'headlesswc_handle_order_request',
                'permission_callback' => 'headlesswc_auth_permission_callback',

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

        register_rest_route(
            'headless-wc/v1',
            '/register',
            array(
                'methods' => 'POST',
                'callback' => 'headlesswc_handle_register_customer',
                'permission_callback' => function () {
                    // Wymagane: włączone ustawienie + ogólne zabezpieczenia domeną działają w globalnym filtrze
                    return get_option('headlesswc_enable_customer_registration', 'no') === 'yes';
                },
            )
        );

        register_rest_route(
            'headless-wc/v1',
            '/auth/status',
            array(
                'methods' => 'GET',
                'callback' => function () {
                    return headlesswc_success_response([
                        'isLoggedIn' => is_user_logged_in(),
                        'userId' => get_current_user_id(),
                        'userLogin' => is_user_logged_in() ? wp_get_current_user()->user_login : null,
                        'userEmail' => is_user_logged_in() ? wp_get_current_user()->user_email : null,
                    ]);
                },
                'permission_callback' => 'headlesswc_auth_permission_callback',
            )
        );
    }
);
