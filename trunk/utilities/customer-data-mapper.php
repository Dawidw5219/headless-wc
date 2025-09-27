<?php
if (! defined('ABSPATH')) {
  exit;
}

/**
 * Universal customer data mapper from camelCase to WooCommerce format
 * Supports multiple output formats: order addresses, user meta, or raw mapping
 * 
 * @param array $data Input data with camelCase keys
 * @param bool $is_shipping Whether this is shipping data (default: false for billing)
 * @param string $output_format Format: 'order' (for WC_Order), 'user_meta' (for user registration), 'raw' (direct mapping)
 * @return array Mapped data in requested format
 */
function headlesswc_map_customer_data($data, $is_shipping = false, $output_format = 'order')
{
  if (empty($data) || !is_array($data)) {
    return [];
  }

  $field_mapping = [
    'firstName' => 'first_name',
    'lastName' => 'last_name',
    'company' => 'company',
    'phone' => 'phone',
    'address1' => 'address_1',
    'address2' => 'address_2',
    'city' => 'city',
    'postcode' => 'postcode',
    'country' => 'country',
    'state' => 'state',
    'email' => 'email',
  ];

  $mapped_data = [];

  switch ($output_format) {
    case 'order':
      // For WC_Order->set_address() - expects direct field names
      // BREAKING CHANGE: expects nested objects: billing{...}, shipping{...}
      $address_key = $is_shipping ? 'shipping' : 'billing';
      if (! array_key_exists($address_key, $data) || ! is_array($data[$address_key])) {
        return [];
      }
      $address_data = $data[$address_key];
      foreach ($field_mapping as $camel_key => $snake_key) {
        if (array_key_exists($camel_key, $address_data)) {
          $value = $address_data[$camel_key];
          $mapped_data[$snake_key] = ($snake_key === 'email')
            ? sanitize_email($value ?? '')
            : sanitize_text_field($value ?? '');
        }
      }
      break;

    case 'user_meta':
      // For user registration - expects prefixed meta keys
      $prefix = $is_shipping ? 'shipping_' : 'billing_';
      foreach ($field_mapping as $camel_key => $snake_key) {
        if (array_key_exists($camel_key, $data)) {
          $value = is_string($data[$camel_key]) ? sanitize_text_field($data[$camel_key]) : (string)$data[$camel_key];
          $mapped_data[$prefix . $snake_key] = $value;
        }
      }
      break;

    case 'raw':
      // Direct mapping without prefixes
      foreach ($field_mapping as $camel_key => $snake_key) {
        if (array_key_exists($camel_key, $data)) {
          $value = ($snake_key === 'email')
            ? sanitize_email($data[$camel_key] ?? '')
            : sanitize_text_field($data[$camel_key] ?? '');
          $mapped_data[$snake_key] = $value;
        }
      }
      break;
  }

  return $mapped_data;
}

/**
 * Legacy wrapper for backward compatibility - maps to user meta format
 * @deprecated Use headlesswc_map_customer_data($data, $is_shipping, 'user_meta') instead
 */
function headlesswc_map_customer_data_to_user_meta($data, $is_shipping = false)
{
  return headlesswc_map_customer_data($data, $is_shipping, 'user_meta');
}
