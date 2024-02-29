<?php
function handle_order_request(WP_REST_Request $request)
{
    try {
        $data = $request->get_json_params();
        $order = wc_create_order();
        $order->update_status('pending');
        update_post_meta($order->get_id(), '_terms_accepted', 'yes');


        if (!empty($data['furgonetkaPoint']) && !empty($data['furgonetkaPointName'])) {
            $order->update_meta_data('_furgonetkaPoint', sanitize_text_field($data['furgonetkaPoint']));
            $order->update_meta_data('_furgonetkaPointName', sanitize_text_field($data['furgonetkaPointName']));
            $order->update_meta_data('_furgonetkaService', sanitize_text_field("inpost"));
        }

        if (empty($data['redirect_url'])) {
            return new WP_REST_Response(['error' => 'No valid redirect URL (client will be redirected after making payment)'], 400);
        } else {
            $order->add_meta_data('redirect_url', $data['redirect_url'], true);
            $order->save();
        }

        if (!apply_cart_products($data['cart'], $order)) {
            return new WP_REST_Response(['error' => 'No valid products in order'], 400);
        }

        $order->set_address(map_customer_data($data), 'billing');
        $order->set_address(!empty($data['use_different_shipping']) ? map_customer_data($data, true) : map_customer_data($data), 'shipping');

        if (!apply_shipping_method($data['shipping_method_id'], $order)) {
            return new WP_REST_Response(['error' => 'Invalid or non-existent shipping method'], 400);
        }

        apply_cupon($data['coupon_code'], $order);


        $payment_method = sanitize_text_field($data['payment_method_id'] ?? '');
        if (!$payment_method || !array_key_exists($payment_method, WC()->payment_gateways->payment_gateways())) {
            return new WP_REST_Response(['error' => 'Invalid payment method'], 400);
        }

        $order->set_payment_method($payment_method);
        $order->calculate_totals();
        $client_total = floatval($data['total'] ?? '0');
        $server_total = floatval($order->get_total());
        if (abs($client_total - $server_total) > 0.01) {
            if (isset($order) && $order->get_id() > 0) {
                wp_delete_post($order->get_id(), true);
            }
            return new WP_REST_Response(['error' => 'Total mismatch. Client: ' . $client_total . ', Server: ' . $server_total], 400);
        }


        $response_data = [
            'success' => true,
            'orderId' => $order->get_id(),
            'paymentUrl' => $order->get_checkout_payment_url(true)
        ];

        return new WP_REST_Response($response_data, 200);
    } catch (Exception $e) {
        return new WP_REST_Response(['error' => 'An error occurred: ' . $e->getMessage()], 500);
    }
}

function map_customer_data($data, $is_shipping = false)
{
    $prefix = $is_shipping ? 'shipping_' : 'billing_';
    return [
        'first_name' => sanitize_text_field($data[$prefix . 'first_name'] ?? ''),
        'last_name' => sanitize_text_field($data[$prefix . 'last_name'] ?? ''),
        'company' => sanitize_text_field($data[$prefix . 'company'] ?? ''),
        'email' => sanitize_email($data[$prefix . 'email'] ?? ''),
        'phone' => sanitize_text_field($data[$prefix . 'phone'] ?? ''),
        'address_1' => sanitize_text_field($data[$prefix . 'address_1'] ?? ''),
        'address_2' => sanitize_text_field($data[$prefix . 'address_2'] ?? ''),
        'city' => sanitize_text_field($data[$prefix . 'city'] ?? ''),
        'state' => sanitize_text_field($data[$prefix . 'state'] ?? ''),
        'postcode' => sanitize_text_field($data[$prefix . 'postcode'] ?? ''),
        'country' => sanitize_text_field($data[$prefix . 'country'] ?? '')
    ];
}

function apply_shipping_method($shipping_method_id, $order)
{
    if (empty($shipping_method_id)) {
        return false;
    }
    list($method_id, $instance_id) = explode(':', $shipping_method_id) + [null, null];
    $shipping_zones = WC_Shipping_Zones::get_zones();
    foreach ($shipping_zones as $zone_data) {
        foreach ($zone_data['shipping_methods'] as $shipping_method) {
            if ($shipping_method->id . ':' . $shipping_method->instance_id === $shipping_method_id) {
                $item = new WC_Order_Item_Shipping();
                $item->set_method_title($shipping_method->title);
                $item->set_method_id($shipping_method_id);
                $item->set_total($shipping_method->cost);
                $order->add_item($item);
                return true;
            }
        }
    }
    return false;
}

function apply_cupon($coupon_code, $order)
{
    if (empty($coupon_code) || !is_string($coupon_code))
        return false;
    if (!$order->apply_coupon(sanitize_text_field($coupon_code))) {
        return false;
    }
    return true;
}

function apply_cart_products($cart, $order)
{
    if (empty($cart) || !is_array($cart)) {
        return false;
    }
    foreach ($cart as $product) {
        $product_id = isset($product['id']) ? intval($product['id']) : 0;
        $quantity = isset($product['quantity']) ? intval($product['quantity']) : 1;
        if ($product_id <= 0 || $quantity <= 0) {
            continue;
        }
        $order->add_product(wc_get_product($product_id), $quantity);
    }
    if (count($order->get_items()) == 0) {
        return false;
    }
    return true;
}