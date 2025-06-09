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
			wp_redirect($redirect_url);
			exit;
		}
	}

	// Handle order-pay page (for COD and other offline payment methods)
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
				wp_redirect($redirect_url);
				exit;
			}

			// For COD orders on payment page, inject automatic redirect JavaScript
			if ($payment_method === 'cod' && $order->get_status() === 'pending') {
				add_action('wp_footer', function () use ($redirect_url, $order_id, $order) {
					$order_key = $order->get_order_key();
?>
					<script type="text/javascript">
						jQuery(document).ready(function($) {
							// Auto-process COD order
							var processedKey = 'headless_wc_cod_processed_<?php echo esc_js($order_id); ?>';

							if (!sessionStorage.getItem(processedKey)) {
								sessionStorage.setItem(processedKey, '1');

								// Find and auto-click the "Place order" or "Pay" button for COD
								setTimeout(function() {
									var payButton = $('#place_order, input[name="woocommerce_pay"], .button[name="woocommerce_pay"]');
									if (payButton.length > 0) {
										payButton.trigger('click');
									} else {
										// If no button found, try to redirect directly
										window.location.href = '<?php echo esc_js($redirect_url); ?>';
									}
								}, 500);
							}

							// Listen for successful order placement
							$(document.body).on('payment_method_selected checkout_place_order_success', function() {
								setTimeout(function() {
									window.location.href = '<?php echo esc_js($redirect_url); ?>';
								}, 1000);
							});
						});
					</script>
				<?php
				});
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

	// For COD orders that just got confirmed, trigger redirect on next page load
	if (!empty($redirect_url) && $payment_method === 'cod' && $old_status === 'pending' && in_array($new_status, array('processing', 'on-hold'))) {
		// Store redirect flag for next page load
		set_transient('headlesswc_redirect_' . $order_id, $redirect_url, 300); // 5 minutes expiry
	}
}

// Check for pending redirects
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
					window.location.href = '<?php echo esc_js($redirect_url); ?>';
				</script>
<?php
			}
		}
	}
}
