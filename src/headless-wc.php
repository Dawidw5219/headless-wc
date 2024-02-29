<?php
/**
 * Plugin Name: HeadlessWC: Ultimate eCommerce Decoupler
 * Description: Custom WC endpoints for headless checkout
 * Version: 1.0.1
 * Author: Dawid Wiewiórski
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
	exit;
}

add_action('plugins_loaded', 'check_woocommerce_active', 0);
function check_woocommerce_active()
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

require_once 'v1/cart.php';
require_once 'v1/order.php';

add_action('rest_api_init', function () {
	register_rest_route(
		'headless-wc/v1',
		'/cart',
		array (
			'methods' => 'POST',
			'callback' => 'handle_cart_request',
		)
	);

	register_rest_route(
		'headless-wc/v1',
		'/order',
		array (
			'methods' => 'POST',
			'callback' => 'handle_order_request',
		)
	);
});



add_action('template_redirect', 'custom_redirect_after_order_received');
function custom_redirect_after_order_received()
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