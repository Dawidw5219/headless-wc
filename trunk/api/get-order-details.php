<?php
if (! defined('ABSPATH')) {
    exit;
}

function headlesswc_handle_order_details_request(WP_REST_Request $request)
{
    try {
        $order_id = intval($request->get_param('order_id') ?? 0);
        $order_key = sanitize_text_field($request->get_param('key') ?? '');

        if (! $order_id || ! $order_key) {
            return new WP_REST_Response(['error' => 'Missing order ID or order key'], 400);
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return new WP_REST_Response(['error' => 'Order not found'], 404);
        }

        // Verify order key matches
        if ($order->get_order_key() !== $order_key) {
            return new WP_REST_Response(['error' => 'Invalid order key'], 403);
        }

        // Prepare order items
        $items = array();
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            $items[] = array(
                'id' => $item->get_product_id(),
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'price' => floatval($item->get_total()),
                'unit_price' => floatval($item->get_total() / $item->get_quantity()),
                'sku' => $product ? $product->get_sku() : null,
                'image' => $product && $product->get_image_id() ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : null,
            );
        }

        // Prepare order data
        $order_data = array(
            'id' => $order->get_id(),
            'order_key' => $order->get_order_key(),
            'status' => $order->get_status(),
            'currency' => $order->get_currency(),
            'date_created' => $order->get_date_created()->format('c'),
            'date_modified' => $order->get_date_modified()->format('c'),
            'payment_method' => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'total' => floatval($order->get_total()),
            'subtotal' => floatval($order->get_subtotal()),
            'total_tax' => floatval($order->get_total_tax()),
            'shipping_total' => floatval($order->get_shipping_total()),
            'discount_total' => floatval($order->get_discount_total()),
            'items' => $items,
            'billing' => array(
                'first_name' => $order->get_billing_first_name(),
                'last_name' => $order->get_billing_last_name(),
                'company' => $order->get_billing_company(),
                'address_1' => $order->get_billing_address_1(),
                'address_2' => $order->get_billing_address_2(),
                'city' => $order->get_billing_city(),
                'state' => $order->get_billing_state(),
                'postcode' => $order->get_billing_postcode(),
                'country' => $order->get_billing_country(),
                'email' => $order->get_billing_email(),
                'phone' => $order->get_billing_phone(),
            ),
            'shipping' => array(
                'first_name' => $order->get_shipping_first_name(),
                'last_name' => $order->get_shipping_last_name(),
                'company' => $order->get_shipping_company(),
                'address_1' => $order->get_shipping_address_1(),
                'address_2' => $order->get_shipping_address_2(),
                'city' => $order->get_shipping_city(),
                'state' => $order->get_shipping_state(),
                'postcode' => $order->get_shipping_postcode(),
                'country' => $order->get_shipping_country(),
            ),
            'customer_note' => $order->get_customer_note(),
        );

        // Add custom fields if any
        $custom_fields = array();
        $meta_data = $order->get_meta_data();
        foreach ($meta_data as $meta) {
            $key = $meta->key;
            $value = $meta->value;

            // Skip internal WooCommerce meta and our redirect_url
            if (! str_starts_with($key, '_') && $key !== 'redirect_url') {
                $custom_fields[$key] = $value;
            }
        }

        if (! empty($custom_fields)) {
            $order_data['custom_fields'] = $custom_fields;
        }

        $response_data = array(
            'success' => true,
            'order' => $order_data,
        );

        return new WP_REST_Response($response_data, 200);
    } catch (Exception $e) {
        return new WP_REST_Response(['error' => 'An error occurred: ' . $e->getMessage()], 500);
    }
}
