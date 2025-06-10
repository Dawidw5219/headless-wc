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

// Hijack order-pay page for headless orders - immediate payment processing
add_action('template_redirect', 'headlesswc_hijack_order_pay', 1);
// Early redirect hook to catch COD orders before any page content is processed
add_action('template_redirect', 'headlesswc_early_cod_redirect', 5);

function headlesswc_hijack_order_pay()
{
	if (!function_exists('is_wc_endpoint_url') || !function_exists('wc_get_order')) {
		return;
	}

	// Only handle order-pay pages
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
		return; // Not a headless order
	}

	// For headless orders, bypass normal WooCommerce template completely
	// and process payment immediately

	// Automatically accept terms
	update_post_meta($order_id, '_terms_accepted', 'yes');

	// Get payment gateway
	$payment_method = $order->get_payment_method();
	$available_gateways = WC()->payment_gateways->get_available_payment_gateways();

	if (!isset($available_gateways[$payment_method])) {
		wp_die('Payment method not available');
	}

	$gateway = $available_gateways[$payment_method];

	// Check if we can process directly or need form submission
	if (isset($_POST['woocommerce_pay']) && isset($_POST['payment_method'])) {
		// Already processing - let WooCommerce handle it normally
		return;
	}

	// For fresh page loads, try direct processing first
	try {
		// Set up session and customer data
		if (!WC()->session) {
			WC()->session = new WC_Session_Handler();
			WC()->session->init();
		}

		WC()->customer->set_billing_country($order->get_billing_country());
		WC()->customer->set_billing_state($order->get_billing_state());
		WC()->customer->set_billing_postcode($order->get_billing_postcode());

		// Simulate form submission
		$_POST['payment_method'] = $payment_method;
		$_POST['terms'] = 'yes';
		$_POST['woocommerce_pay'] = '1';
		$_POST['woocommerce-pay-nonce'] = wp_create_nonce('woocommerce-pay');

		// Try to process payment
		$result = $gateway->process_payment($order_id);

		if (isset($result['result']) && $result['result'] === 'success' && !empty($result['redirect'])) {
			// Direct redirect to payment gateway
			wp_redirect($result['redirect']);
			exit;
		}
	} catch (Exception $e) {
		// Log error but continue to fallback
		error_log('HeadlessWC: Direct payment processing failed - ' . $e->getMessage());
	}

	// Fallback: show minimal auto-submit form
	headlesswc_show_minimal_payment_page($order, $gateway);
	exit;
}

function headlesswc_show_minimal_payment_page($order, $gateway, $error = '')
{
	// Minimal payment page for headless orders - no theme, just essentials
	?>
	<!DOCTYPE html>
	<html>

	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<title>Payment Processing</title>
		<style>
			body {
				font-family: Arial, sans-serif;
				margin: 0;
				padding: 20px;
				background: #f5f5f5
			}

			.container {
				max-width: 600px;
				margin: 0 auto;
				background: white;
				padding: 30px;
				border-radius: 8px
			}

			.error {
				background: #ffebee;
				color: #c62828;
				padding: 15px;
				border-radius: 4px;
				margin-bottom: 20px
			}

			form {
				margin: 20px 0
			}

			input,
			button {
				display: block;
				width: 100%;
				padding: 12px;
				margin: 10px 0;
				border: 1px solid #ddd;
				border-radius: 4px
			}

			button {
				background: #0073aa;
				color: white;
				cursor: pointer;
				font-size: 16px
			}

			button:hover {
				background: #005177
			}
		</style>
	</head>

	<body>
		<div class="container">
			<h2>Processing Payment</h2>
			<?php if ($error): ?>
				<div class="error"><?php echo esc_html($error); ?></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url($order->get_checkout_payment_url(true)); ?>">
				<?php wp_nonce_field('woocommerce-pay', 'woocommerce-pay-nonce'); ?>
				<input type="hidden" name="payment_method" value="<?php echo esc_attr($order->get_payment_method()); ?>">
				<input type="hidden" name="terms" value="yes">
				<input type="hidden" name="woocommerce_pay" value="1">

				<p><strong>Order #<?php echo $order->get_order_number(); ?></strong></p>
				<p>Total: <?php echo $order->get_formatted_order_total(); ?></p>
				<p>Payment Method: <?php echo $gateway->title; ?></p>

				<button type="submit" id="pay-btn">Pay Now</button>
			</form>
		</div>

		<script>
			// Instant auto-submit
			document.getElementById('pay-btn').click();
		</script>
	</body>

	</html>
<?php
}

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
