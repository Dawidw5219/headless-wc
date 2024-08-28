<?php
/**
 * Plugin Name: HeadlessWC: Ultimate eCommerce Decoupler
 * Description: Custom WC endpoints for headless checkout
 * Version: 1.0.6
 * Author: Dawid Wiewiórski
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action('plugins_loaded', 'headlesswc_check_woocommerce_active', 0);
function headlesswc_check_woocommerce_active()
{
	include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	if (!class_exists('WooCommerce')) {
		if (is_plugin_active(plugin_basename(__FILE__))) {
			deactivate_plugins(plugin_basename(__FILE__));
			add_action('admin_notices', function () {
				echo '<div class="error"><p>' . esc_html__("HeadlessWC plugin cannot operate without WooCommerce being enabled. Please install and activate the WooCommerce plugin", "headless-wc-no-woocommerce") . '</p></div>';
			});
		}
	}
}

require_once 'api/v1/cart.php';
require_once 'api/v1/order.php';
require_once 'api/v1/products/get-all-products.php';
require_once 'api/v1/products/get-single-product.php';

add_action('rest_api_init', function () {

	if (!class_exists('WooCommerce') || !WC()->cart) {
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

	register_rest_route('headless-wc/v1', '/products/(?P<slug>[a-zA-Z0-9-]+)', array(
		'methods' => 'GET',
		'callback' => 'headlesswc_handle_product_request',
	));
});



add_action('template_redirect', 'headlesswc_redirect_after_order_received');
function headlesswc_redirect_after_order_received()
{
	if (is_wc_endpoint_url('order-received')) {
		$order_id = isset($GLOBALS['wp']->query_vars['order-received']) ? intval($GLOBALS['wp']->query_vars['order-received']) : false;
		if (!$order_id) {
			return;
		}
		$order = wc_get_order($order_id);
		if (!$order) {
			return;
		}
		$redirect_url = $order->get_meta('redirect_url');
		if (!empty($redirect_url)) {
			wp_redirect($redirect_url);
			exit;
		}
	}
}