<?php
function headlesswc_handle_cart_request(WP_REST_Request $request)
{
    try {
        $data = $request->get_json_params();
        $currency = get_woocommerce_currency();
        $cart = WC()->cart;
        $cart->empty_cart();
        $discount_total = 0;

        foreach ($data['cart'] as $product) {
            $product_id = isset($product['id']) ? intval($product['id']) : 0;
            $quantity = isset($product['quantity']) ? intval($product['quantity']) : 1;
            if (!$product_id || $quantity < 1) {
                continue;
            }
            $cart->add_to_cart($product_id, $quantity);
        }

        if (isset($data['couponCode']) && !empty($data['couponCode'])) {
            if ($cart->apply_coupon($data['couponCode'])) {
                $discount_total = $cart->get_discount_total();
            } else {
                unset($data['couponCode']);
            }
        }
        $cart->calculate_totals();

        $cart_items = [];
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $item_data = $cart_item['data'];
            $regular_price = $item_data->get_regular_price();
            $sale_price = $item_data->get_sale_price() ? $item_data->get_sale_price() : $regular_price;
            $cart_items[] = [
                'id' => $cart_item['product_id'],
                'name' => $item_data->get_name(),
                'img' => wp_get_attachment_image_url($item_data->get_image_id(), apply_filters('single_product_archive_thumbnail_size', 'woocommerceThumbnail')),
                'fullImg' => wp_get_attachment_url($item_data->get_image_id()),
                'quantity' => $cart_item['quantity'],
                'price' => $item_data->is_on_sale() ? floatval($sale_price) : floatval($regular_price),
                'regularPrice' => floatval($regular_price),
                'salePrice' => floatval($sale_price),
                'isOnsale' => $item_data->is_on_sale(),
                'total' => $cart_item['line_total'],
                'tax' => $cart_item['line_tax'],
            ];
        }

        $shipping_methods = [];
        $packages = WC()->shipping->get_packages();
        $zones = WC_Shipping_Zones::get_zones();
        foreach ($packages as $package) {
            foreach ($package['rates'] as $rate) {
                $zone_found = false;
                foreach ($zones as $zone) {
                    foreach ($zone['shipping_methods'] as $method) {
                        if ($method->id == $rate->method_id) {
                            $zone_id = $zone['id'];
                            $zone_name = $zone['zone_name'];
                            $zone_found = true;
                            break;
                        }
                    }
                    if ($zone_found) {
                        $zone = WC_Shipping_Zones::get_zone($zone_id);
                        $locations = $zone->get_zone_locations();
                        $zone_locations_info = array_map(function ($location) {
                            return ['type' => $location->type, 'code' => $location->code];
                        }, $locations);
                        $taxes = $rate->get_taxes();
                        $shipping_methods[] = [
                            'name' => $rate->get_label(),
                            'id' => $rate->get_id(),
                            'price' => floatval($rate->get_cost()),
                            'tax' => array_sum($taxes),
                            'zone' => $zone_name,
                            'locations' => $zone_locations_info,
                        ];
                        break;
                    }
                }
            }
        }

        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $payment_methods = [];
        foreach ($available_gateways as $gateway_id => $gateway) {
            $payment_methods[] = [
                'id' => $gateway_id,
                'title' => $gateway->title,
                'description' => $gateway->description,
            ];
        }

        $response_data = [
            "success" => true,
            'products' => $cart_items,
            'subtotal' => floatval($cart->get_subtotal()),
            'total' => floatval($cart->get_total('edit')),
            'taxTotal' => floatval($cart->get_total_tax()),
            'shippingTotal' => floatval($shipping_methods[0]['price']),
            'discountTotal' => floatval($discount_total),
            'couponCode' => isset($data['couponCode']) ? $data['couponCode'] : '',
            'currency' => $currency,
            'availableShippingMethods' => $shipping_methods,
            'availablePaymentMethods' => $payment_methods,
        ];

        return new WP_REST_Response($response_data, 200);
    } catch (Exception $e) {
        return new WP_REST_Response(array('error' => 'Unexpected error'), 500);
    }
}
