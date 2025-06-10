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

// Enhanced automatic payment processing
add_action('wp_footer', 'headlesswc_auto_payment_processor');

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
		<script type="text/javascript">
			(function() {
				let processed = false;

				function autoProcessPayment() {
					if (processed) return;
					processed = true;

					console.log('HeadlessWC: Processing headless order payment automatically...');

					const executePayment = () => {
						// Step 1: Handle terms and conditions with comprehensive selectors
						const termsSelectors = [
							'#terms', 'input[name="terms"]', 'input[name="terms_and_conditions"]',
							'.terms input[type="checkbox"]', '.legal input[type="checkbox"]',
							'#legal', 'input[id*="terms"]', 'input[class*="terms"]',
							'.woocommerce-terms-and-conditions input', '.form-row.terms input',
							'input[name*="woocommerce_checkout_terms"]', '.checkout-terms input'
						];

						for (const selector of termsSelectors) {
							const termsEl = document.querySelector(selector);
							if (termsEl && termsEl.type === 'checkbox' && !termsEl.checked) {
								termsEl.checked = true;
								termsEl.dispatchEvent(new Event('change', {
									bubbles: true
								}));
								console.log('HeadlessWC: Terms accepted automatically');
								break;
							}
						}

						// Step 2: Ensure payment method is selected
						const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
						if (paymentMethods.length > 0) {
							const hasSelected = Array.from(paymentMethods).some(m => m.checked);
							if (!hasSelected) {
								paymentMethods[0].checked = true;
								paymentMethods[0].dispatchEvent(new Event('change', {
									bubbles: true
								}));
								console.log('HeadlessWC: Payment method auto-selected');
							}
						}

						// Step 3: Submit payment form immediately (natural user behavior)
						setTimeout(() => {
							// Comprehensive button selectors for various payment gateways and themes
							const buttonSelectors = [
								// Standard WooCommerce
								'#place_order', 'button#place_order', 'input#place_order',
								// Order review forms
								'form#order_review input[type="submit"]', 'form#order_review button[type="submit"]',
								// Payment specific
								'.woocommerce-checkout-payment #place_order', '.payment-box input[type="submit"]',
								// PayU specific
								'input[value*="Zapłać"]', 'input[value*="PayU"]', '.payu-button',
								'input[name="pay"]', 'button[name="pay"]',
								// Multilingual support
								'input[value*="Pay now"]', 'input[value*="Complete order"]', 'input[value*="Place order"]',
								'input[value*="Pagar"]', 'input[value*="Pagamento"]', 'input[value*="Bezahlen"]',
								// Generic payment buttons
								'.place-order input', '.place-order button', '#payment input[type="submit"]',
								'#payment button[type="submit"]', '.payment-method input[type="submit"]',
								// Gateway specific
								'#stripe-submit', '#paypal-submit', '#przelewy24-submit',
								// Theme specific fallbacks
								'.wc-proceed-to-checkout', '.checkout-button',
								// Last resort - any submit button in payment area
								'form input[type="submit"]:visible', 'form button[type="submit"]:visible'
							];

							let buttonClicked = false;
							for (const selector of buttonSelectors) {
								const button = document.querySelector(selector);
								if (button && button.offsetParent !== null && !button.disabled) {
									const form = button.closest('form') || document.querySelector('#order_review, .checkout, .woocommerce-checkout');
									if (form) {
										// Quick validation - ensure no obviously empty required fields
										const emptyRequired = Array.from(form.querySelectorAll('input[required]:not([type="checkbox"]), select[required]'))
											.some(field => !field.value.trim());

										if (!emptyRequired) {
											console.log('HeadlessWC: Clicking payment button -', selector);
											// Natural click simulation
											button.focus();
											setTimeout(() => {
												if (button.click) {
													button.click();
												} else {
													// Fallback for custom implementations
													const clickEvent = new MouseEvent('click', {
														bubbles: true,
														cancelable: true,
														view: window
													});
													button.dispatchEvent(clickEvent);
												}
											}, 50);
											buttonClicked = true;
											break;
										}
									}
								}
							}

							if (!buttonClicked) {
								console.warn('HeadlessWC: No suitable payment button found, retrying in 800ms...');
								setTimeout(executePayment, 800);
							}
						}, 150); // Minimal delay to simulate human behavior
					};

					// Execute based on page state
					if (document.readyState === 'loading') {
						document.addEventListener('DOMContentLoaded', () => setTimeout(executePayment, 100));
					} else {
						setTimeout(executePayment, 100);
					}
				}

				// Start processing quickly to minimize user-visible delay
				setTimeout(autoProcessPayment, 200);
			})();
		</script>
		<?php
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
