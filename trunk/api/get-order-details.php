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
            return headlesswc_error_response(
                'Brak ID zamówienia lub klucza zamówienia',
                HeadlessWC_Error_Codes::MISSING_ORDER_DATA
            );
        }

        $order = wc_get_order($order_id);
        if (! $order) {
            return headlesswc_error_response(
                'Zamówienie nie zostało znalezione',
                HeadlessWC_Error_Codes::ORDER_NOT_FOUND,
                404
            );
        }

        // Verify order key matches
        if ($order->get_order_key() !== $order_key) {
            return headlesswc_error_response(
                'Nieprawidłowy klucz zamówienia',
                HeadlessWC_Error_Codes::INVALID_ORDER_KEY,
                403
            );
        }

        // Prepare order items
        $items = array();
        foreach ($order->get_items() as $item) {
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

            // Skip internal WooCommerce meta and our headlesswc_redirect_url
            if (substr($key, 0, 1) !== '_' && $key !== 'headlesswc_redirect_url') {
                $custom_fields[$key] = $value;
            }
        }

        if (! empty($custom_fields)) {
            $order_data['custom_fields'] = $custom_fields;
        }

        return headlesswc_success_response([
            'order' => $order_data,
        ]);
    } catch (Exception $e) {
        return headlesswc_error_response(
            'Wystąpił nieoczekiwany błąd: ' . $e->getMessage(),
            HeadlessWC_Error_Codes::UNEXPECTED_ERROR,
            500
        );
    }
}
