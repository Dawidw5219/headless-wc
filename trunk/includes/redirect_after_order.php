<?php
if (! defined('ABSPATH')) {
	exit;
}

function headlesswc_redirect_after_order()
{
	if (! function_exists('is_wc_endpoint_url') || ! function_exists('wc_get_order')) {
		return;
	}

	// Handle order-received page (after successful payment)
	if (is_wc_endpoint_url('order-received')) {
		$order_id = isset($GLOBALS['wp']->query_vars['order-received']) ? intval($GLOBALS['wp']->query_vars['order-received']) : false;
		if (! $order_id) {
			return;
		}
		$order = wc_get_order($order_id);
		if (! $order) {
			return;
		}
		$redirect_url = $order->get_meta('redirect_url');
		if (! empty($redirect_url)) {
			// Add order key and order ID as query parameters - fix URL encoding
			$redirect_url = add_query_arg(array(
				'order' => $order_id,
				'key' => $order->get_order_key()
			), $redirect_url);
			wp_redirect($redirect_url);
			exit;
		}
	}

	// Handle order-pay page - NEW PHP-based approach for COD
	if (is_wc_endpoint_url('order-pay')) {
		$order_id = isset($GLOBALS['wp']->query_vars['order-pay']) ? intval($GLOBALS['wp']->query_vars['order-pay']) : false;
		if (! $order_id) {
			return;
		}
		$order = wc_get_order($order_id);
		if (! $order) {
			return;
		}

		$redirect_url = $order->get_meta('redirect_url');
		$payment_method = $order->get_payment_method();

		if (! empty($redirect_url)) {
			// For already confirmed orders, redirect immediately
			if (in_array($order->get_status(), array('processing', 'on-hold', 'completed'))) {
				$redirect_url_with_params = add_query_arg(array(
					'order' => $order_id,
					'key' => $order->get_order_key()
				), $redirect_url);
				wp_redirect($redirect_url_with_params);
				exit;
			}

			// NEW: PHP-based COD handling - no JavaScript needed
			if ($payment_method === 'cod' && $order->get_status() === 'pending') {
				// Automatically confirm COD order
				$order->update_status('processing', __('COD order automatically confirmed for headless checkout.', 'headless-wc'));

				// Add order key and order ID as query parameters
				$redirect_url_with_params = add_query_arg(array(
					'order' => $order_id,
					'key' => $order->get_order_key()
				), $redirect_url);

				// Immediate redirect - no page content will be displayed
				wp_redirect($redirect_url_with_params);
				exit;
			}
		}
	}
}

// Hook into WooCommerce order status changes to handle redirects
add_action('woocommerce_order_status_changed', 'headlesswc_handle_order_status_change', 10, 4);

function headlesswc_handle_order_status_change($order_id, $old_status, $new_status, $order)
{
	$redirect_url = $order->get_meta('redirect_url');
	$payment_method = $order->get_payment_method();

	// For non-COD orders that just got confirmed, set up redirect for next page load
	if (!empty($redirect_url) && $payment_method !== 'cod' && $old_status === 'pending' && in_array($new_status, array('processing', 'on-hold', 'completed'))) {
		// Add order key and order ID as query parameters
		$redirect_url_with_params = add_query_arg(array(
			'order' => $order_id,
			'key' => $order->get_order_key()
		), $redirect_url);
		// Store redirect flag for next page load
		set_transient('headlesswc_redirect_' . $order_id, $redirect_url_with_params, 300); // 5 minutes expiry
	}
}

// Check for pending redirects (for non-COD payments)
add_action('wp_footer', 'headlesswc_check_pending_redirect');

function headlesswc_check_pending_redirect()
{
	if (is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('order-received')) {
		$order_id = 0;

		if (is_wc_endpoint_url('order-pay')) {
			$order_id = isset($GLOBALS['wp']->query_vars['order-pay']) ? intval($GLOBALS['wp']->query_vars['order-pay']) : 0;
		} elseif (is_wc_endpoint_url('order-received')) {
			$order_id = isset($GLOBALS['wp']->query_vars['order-received']) ? intval($GLOBALS['wp']->query_vars['order-received']) : 0;
		}

		if ($order_id > 0) {
			$redirect_url = get_transient('headlesswc_redirect_' . $order_id);
			if (!empty($redirect_url)) {
				delete_transient('headlesswc_redirect_' . $order_id);
?>
				<script type="text/javascript">
					window.location.href = <?php echo json_encode($redirect_url); ?>;
				</script>
<?php
			}
		}
	}
}

// NEW: Early redirect hook to catch COD orders before any page content is processed
add_action('template_redirect', 'headlesswc_early_cod_redirect', 5);

function headlesswc_early_cod_redirect()
{
	if (! function_exists('is_wc_endpoint_url') || ! function_exists('wc_get_order')) {
		return;
	}

	// Only handle order-pay pages
	if (! is_wc_endpoint_url('order-pay')) {
		return;
	}

	$order_id = isset($GLOBALS['wp']->query_vars['order-pay']) ? intval($GLOBALS['wp']->query_vars['order-pay']) : false;
	if (! $order_id) {
		return;
	}

	$order = wc_get_order($order_id);
	if (! $order) {
		return;
	}

	$redirect_url = $order->get_meta('redirect_url');
	$payment_method = $order->get_payment_method();

	// Early redirect for COD orders with redirect URL set
	if (! empty($redirect_url) && $payment_method === 'cod') {
		// Check if order needs to be confirmed
		if ($order->get_status() === 'pending') {
			// Automatically confirm COD order
			$order->update_status('processing', __('COD order automatically confirmed for headless checkout.', 'headless-wc'));
		}

		// Always redirect if order is ready and has redirect URL
		if (in_array($order->get_status(), array('processing', 'on-hold', 'completed'))) {
			$redirect_url_with_params = add_query_arg(array(
				'order' => $order_id,
				'key' => $order->get_order_key()
			), $redirect_url);

			wp_redirect($redirect_url_with_params);
			exit;
		}
	}
}
