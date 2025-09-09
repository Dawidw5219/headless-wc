<?php
if (! defined('ABSPATH')) {
	exit;
}

/**
 * POST /wp-json/headless-wc/v1/register
 * Body JSON:
 * {
 *   email, password, firstName, lastName, username?, billing: {...}, shipping: {...}, meta: { key: value }
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
			return headlesswc_error_response(__('Failed to create user', 'headless-wc'), HeadlessWC_Error_Codes::CREATE_USER_FAILED, 500, [ 'details' => $user_id->get_error_message() ]);
		}

		// Set first and last name
		update_user_meta($user_id, 'first_name', $first_name);
		update_user_meta($user_id, 'last_name', $last_name);

		// Map and save addresses (billing/shipping)
		$billing = is_array($params['billing'] ?? null) ? $params['billing'] : [];
		$shipping = is_array($params['shipping'] ?? null) ? $params['shipping'] : [];

		$billing_map = [
			'first_name' => 'billing_first_name',
			'last_name' => 'billing_last_name',
			'company' => 'billing_company',
			'phone' => 'billing_phone',
			'address_1' => 'billing_address_1',
			'address_2' => 'billing_address_2',
			'city' => 'billing_city',
			'postcode' => 'billing_postcode',
			'country' => 'billing_country',
			'state' => 'billing_state',
			'email' => 'billing_email',
		];
		$shipping_map = [
			'first_name' => 'shipping_first_name',
			'last_name' => 'shipping_last_name',
			'company' => 'shipping_company',
			'phone' => 'shipping_phone',
			'address_1' => 'shipping_address_1',
			'address_2' => 'shipping_address_2',
			'city' => 'shipping_city',
			'postcode' => 'shipping_postcode',
			'country' => 'shipping_country',
			'state' => 'shipping_state',
			'email' => 'shipping_email',
		];

		foreach ($billing_map as $key => $meta_key) {
			if (array_key_exists($key, $billing)) {
				$value = is_string($billing[$key]) ? sanitize_text_field($billing[$key]) : (string)$billing[$key];
				update_user_meta($user_id, $meta_key, $value);
			}
		}
		foreach ($shipping_map as $key => $meta_key) {
			if (array_key_exists($key, $shipping)) {
				$value = is_string($shipping[$key]) ? sanitize_text_field($shipping[$key]) : (string)$shipping[$key];
				update_user_meta($user_id, $meta_key, $value);
			}
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
