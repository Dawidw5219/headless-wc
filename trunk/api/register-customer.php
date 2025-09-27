<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * POST /wp-json/headless-wc/v1/register
 * Body JSON:
 * {
 *   email, password, firstName, lastName, username?, billing: {firstName, lastName, company, phone, address1, address2, city, postcode, country, state, email}, shipping: {firstName, lastName, company, phone, address1, address2, city, postcode, country, state, email}, meta: { key: value }
 * }
 */
function headlesswc_handle_register_customer(WP_REST_Request $request)
{
	try {
		if (get_option('headlesswc_enable_customer_registration', 'no') !== 'yes') {
			return headlesswc_error_response(
				__('API registration is disabled', 'headless-wc'),
				HeadlessWC_Error_Codes::CUSTOMER_REGISTRATION_DISABLED,
				403
			);
		}

		$params = $request->get_json_params();

		$email = sanitize_email($params['email'] ?? '');
		$password = (string)($params['password'] ?? '');
		$first_name = sanitize_text_field($params['firstName'] ?? '');
		$last_name = sanitize_text_field($params['lastName'] ?? '');
		$username = sanitize_user($params['username'] ?? ($email ? current(explode('@', $email)) : ''));

		if (empty($email) || !is_email($email)) {
			return headlesswc_error_response(__('Invalid email address', 'headless-wc'), HeadlessWC_Error_Codes::INVALID_EMAIL);
		}
		if (email_exists($email) || username_exists($username)) {
			return headlesswc_error_response(__('User already exists', 'headless-wc'), HeadlessWC_Error_Codes::USER_EXISTS, 409);
		}
		if (empty($password) || strlen($password) < 8) {
			return headlesswc_error_response(__('Password must be at least 8 characters', 'headless-wc'), HeadlessWC_Error_Codes::WEAK_PASSWORD);
		}

		$user_id = wc_create_new_customer($email, $username, $password);
		if (is_wp_error($user_id)) {
			return headlesswc_error_response(__('Failed to create user', 'headless-wc'), HeadlessWC_Error_Codes::CREATE_USER_FAILED, 500, ['details' => $user_id->get_error_message()]);
		}

		// Set first and last name
		update_user_meta($user_id, 'first_name', $first_name);
		update_user_meta($user_id, 'last_name', $last_name);

		// Map and save addresses (billing/shipping) using camelCase format
		$billing = is_array($params['billing'] ?? null) ? $params['billing'] : [];
		$shipping = is_array($params['shipping'] ?? null) ? $params['shipping'] : [];

		// Use utility function to map camelCase data to user meta
		$billing_meta = headlesswc_map_customer_data($billing, false, 'user_meta');
		$shipping_meta = headlesswc_map_customer_data($shipping, true, 'user_meta');

		// Save billing meta
		foreach ($billing_meta as $meta_key => $value) {
			update_user_meta($user_id, $meta_key, $value);
		}

		// Save shipping meta
		foreach ($shipping_meta as $meta_key => $value) {
			update_user_meta($user_id, $meta_key, $value);
		}

		// Whitelist user meta
		$meta = is_array($params['meta'] ?? null) ? $params['meta'] : [];
		$allowed_meta_keys = [
			'phone_verified',
			'accepts_marketing',
			'customer_note',
		];
		foreach ($meta as $key => $val) {
			$sanitized_key = sanitize_key($key);
			if (!in_array($sanitized_key, $allowed_meta_keys, true)) {
				continue;
			}
			$sanitized_val = is_string($val) ? sanitize_text_field($val) : (is_bool($val) ? ($val ? 'yes' : 'no') : (string)$val);
			update_user_meta($user_id, $sanitized_key, $sanitized_val);
		}

		// Ensure role customer
		$user = get_user_by('id', $user_id);
		if ($user instanceof WP_User) {
			$user->set_role('customer');
		}

		// Trigger WooCommerce emails according to WooCommerce settings
		// - Customer: "New account" (WC email, respects enable/disable)
		// - Admin: core WordPress admin notification about new user
		if (class_exists('WooCommerce') && function_exists('WC')) {
			$mailer = WC()->mailer();
			if ($mailer && method_exists($mailer, 'get_emails')) {
				$emails = $mailer->get_emails();
				// Find and trigger the customer new account email if enabled
				foreach ((array)$emails as $email_instance) {
					if (
						is_object($email_instance)
						&& property_exists($email_instance, 'id')
						&& $email_instance->id === 'customer_new_account'
						&& method_exists($email_instance, 'is_enabled')
						&& $email_instance->is_enabled()
						&& method_exists($email_instance, 'trigger')
					) {
						$email_instance->trigger($user_id, $password, $user);
						break;
					}
				}
			}
		}

		// Send admin notification via WordPress core (separate from WooCommerce customer email)
		if (function_exists('wp_new_user_notification')) {
			// Notify only admin here to avoid duplicate customer emails
			wp_new_user_notification($user_id, null, 'admin');
		}

		return headlesswc_success_response([
			'userId' => $user_id,
			'email' => $email,
			'username' => $username,
		]);
	} catch (Exception $e) {
		return headlesswc_error_response(
			__('An unexpected error occurred: ', 'headless-wc') . $e->getMessage(),
			HeadlessWC_Error_Codes::UNEXPECTED_ERROR,
			500
		);
	}
}
