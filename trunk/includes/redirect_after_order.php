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
			// Add order key and order ID as query parameters - respect settings
			if (headlesswc_is_include_order_key_enabled()) {
				$redirect_url = add_query_arg(array(
					'order' => $order_id,
					'key' => $order->get_order_key()
				), $redirect_url);
			}
			wp_redirect($redirect_url);
			exit;
		}
	}

	// Handle order-pay page - Enhanced approach for all payment methods
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
				if (headlesswc_is_include_order_key_enabled()) {
					$redirect_url = add_query_arg(array(
						'order' => $order_id,
						'key' => $order->get_order_key()
					), $redirect_url);
				}
				wp_redirect($redirect_url);
				exit;
			}

			// PHP-based COD handling - respect auto-confirm setting
			if ($payment_method === 'cod' && $order->get_status() === 'pending' && headlesswc_is_auto_confirm_cod_enabled()) {
				// Automatically confirm COD order
				$order->update_status('processing', __('COD order automatically confirmed for headless checkout.', 'headless-wc'));

				// Add order key and order ID as query parameters if enabled
				if (headlesswc_is_include_order_key_enabled()) {
					$redirect_url = add_query_arg(array(
						'order' => $order_id,
						'key' => $order->get_order_key()
					), $redirect_url);
				}

				// Immediate redirect - no page content will be displayed
				wp_redirect($redirect_url);
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
		// Add order key and order ID as query parameters if enabled
		$redirect_url_with_params = $redirect_url;
		if (headlesswc_is_include_order_key_enabled()) {
			$redirect_url_with_params = add_query_arg(array(
				'order' => $order_id,
				'key' => $order->get_order_key()
			), $redirect_url);
		}
		// Store redirect flag for next page load
		set_transient('headlesswc_redirect_' . $order_id, $redirect_url_with_params, 300); // 5 minutes expiry
	}
}

// Enhanced automatic payment processing - inject early for speed
add_action('wp_head', 'headlesswc_auto_payment_processor');
// Remove unnecessary assets for headless orders
add_action('wp_enqueue_scripts', 'headlesswc_optimize_headless_checkout', 999);

function headlesswc_auto_payment_processor()
{
	if (!is_wc_endpoint_url('order-pay')) {
		return;
	}



	$order_id = isset($GLOBALS['wp']->query_vars['order-pay']) ? intval($GLOBALS['wp']->query_vars['order-pay']) : 0;
	if ($order_id <= 0) {
		return;
	}

	$order = wc_get_order($order_id);
	if (!$order) {
		return;
	}

	$redirect_url = $order->get_meta('redirect_url');
	if (empty($redirect_url)) {
		return;
	}

	// Check if this is a headless checkout (has redirect_url meta)
	// and order is still pending payment
	if ($order->get_status() === 'pending') {
?>
		<script>
			! function() {
				if (window.hwcProc) return;
				window.hwcProc = 1;
				var p = function() {
					var t = document.querySelector('#terms,input[name="terms"],.terms input[type="checkbox"]');
					t && 'checkbox' === t.type && !t.checked && (t.checked = 1, t.dispatchEvent(new Event('change', {
						bubbles: 1
					})));
					var m = document.querySelectorAll('input[name="payment_method"]');
					if (m.length && ![].some.call(m, function(x) {
							return x.checked
						})) m[0].checked = 1, m[0].dispatchEvent(new Event('change', {
						bubbles: 1
					}));
					var s = ['#place_order', 'input#place_order', 'form#order_review input[type="submit"]', 'input[value*="Zapłać"]', 'input[name="pay"]'];
					for (var i = 0; i < s.length; i++) {
						var b = document.querySelector(s[i]);
						if (b && b.offsetParent && !b.disabled) {
							b.click();
							return;
						}
					}
					setTimeout(p, 300);
				};
				'loading' === document.readyState ? document.addEventListener('DOMContentLoaded', p) : p();
			}();
		</script>
		<?php
	}
}

// Optimize headless checkout performance by removing unnecessary assets
function headlesswc_optimize_headless_checkout()
{
	if (!is_wc_endpoint_url('order-pay')) {
		return;
	}

	$order_id = isset($GLOBALS['wp']->query_vars['order-pay']) ? intval($GLOBALS['wp']->query_vars['order-pay']) : 0;
	if ($order_id <= 0) {
		return;
	}

	$order = wc_get_order($order_id);
	if (!$order || empty($order->get_meta('redirect_url'))) {
		return;
	}

	// Remove unnecessary theme styles and scripts for headless orders
	global $wp_styles, $wp_scripts;

	// Remove theme stylesheets (but keep essential WooCommerce ones)
	$keep_styles = ['woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen'];
	if ($wp_styles) {
		foreach ($wp_styles->registered as $handle => $style) {
			if (
				!in_array($handle, $keep_styles) &&
				(strpos($handle, 'theme') !== false ||
					strpos($handle, get_template()) !== false ||
					strpos($handle, 'elementor') !== false ||
					strpos($handle, 'visual-composer') !== false)
			) {
				wp_dequeue_style($handle);
			}
		}
	}

	// Remove unnecessary scripts but keep payment gateway essentials
	$keep_scripts = ['jquery', 'woocommerce', 'wc-checkout', 'payment'];
	if ($wp_scripts) {
		foreach ($wp_scripts->registered as $handle => $script) {
			if (
				!in_array($handle, $keep_scripts) &&
				(strpos($handle, 'theme') !== false ||
					strpos($handle, get_template()) !== false ||
					strpos($handle, 'slider') !== false ||
					strpos($handle, 'animation') !== false)
			) {
				wp_dequeue_script($handle);
			}
		}
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

// Early redirect hook to catch COD orders before any page content is processed
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

	// Early redirect for COD orders with redirect URL set - respect settings
	if (! empty($redirect_url) && $payment_method === 'cod') {
		// Check if order needs to be confirmed and auto-confirm is enabled
		if ($order->get_status() === 'pending' && headlesswc_is_auto_confirm_cod_enabled()) {
			// Automatically confirm COD order
			$order->update_status('processing', __('COD order automatically confirmed for headless checkout.', 'headless-wc'));
		}

		// Always redirect if order is ready and has redirect URL
		if (in_array($order->get_status(), array('processing', 'on-hold', 'completed'))) {
			// Add order key and order ID as query parameters if enabled
			if (headlesswc_is_include_order_key_enabled()) {
				$redirect_url = add_query_arg(array(
					'order' => $order_id,
					'key' => $order->get_order_key()
				), $redirect_url);
			}

			wp_redirect($redirect_url);
			exit;
		}
	}
}
