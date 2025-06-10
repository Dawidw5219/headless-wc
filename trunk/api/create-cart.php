<?php
if (! defined('ABSPATH')) {
    exit;
}

function headlesswc_handle_cart_request(WP_REST_Request $request)
{
    $start_timer = microtime(true);
    try {
        $data = $request->get_json_params();
        $currency = get_woocommerce_currency();
        $cart = WC()->cart;
        $cart->empty_cart();
        $discount_total = 0;

        // Waliduj produkty używając wspólnej funkcji
        $valid_products = headlesswc_validate_cart_products($data['cart']);

        // Dodaj tylko poprawne produkty do koszyka
        foreach ($valid_products as $product) {
            $product_id = $product['id'];
            $quantity = $product['quantity'];
            $variation_id = isset($product['variation_id']) ? $product['variation_id'] : 0;
            $variation = isset($product['variation']) ? $product['variation'] : [];

            if ($variation_id > 0) {
                $cart->add_to_cart($product_id, $quantity, $variation_id, $variation);
            } else {
                $cart->add_to_cart($product_id, $quantity);
            }
        }

        if (isset($data['couponCode']) && ! empty($data['couponCode'])) {
            if ($cart->apply_coupon($data['couponCode'])) {
                $discount_total = $cart->get_discount_total();
            } else {
                unset($data['couponCode']);
            }
        }
        $cart->calculate_totals();

        $cart_items = [];
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $product = new HWC_Cart_Product($cart_item);
            $cart_items[] = $product->get_data();
        }

        $shipping_methods = [];
        $packages = WC()->shipping->get_packages();
        $zones = WC_Shipping_Zones::get_zones();
        foreach ($packages as $package) {
            foreach ($package['rates'] as $rate) {
                $zone_found = false;
                foreach ($zones as $zone) {
                    foreach ($zone['shipping_methods'] as $method) {
                        if ($method->id === $rate->method_id) {
                            $zone_id = $zone['id'];
                            $zone_name = $zone['zone_name'];
                            $zone_found = true;
                            break;
                        }
                    }
                    if ($zone_found) {
                        $zone = WC_Shipping_Zones::get_zone($zone_id);
                        $locations = $zone->get_zone_locations();
                        $zone_locations_info = array_map(
                            function ($location) {
                                return [
                                    'type' => $location->type,
                                    'code' => $location->code,
                                ];
                            },
                            $locations
                        );
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

        return headlesswc_success_response([
            'products' => $cart_items,
            'subtotal' => floatval($cart->get_subtotal()),
            'total' => floatval($cart->get_total('edit')),
            'taxTotal' => floatval($cart->get_total_tax()),
            'shippingTotal' => floatval($shipping_methods[0]['price']),
            'discountTotal' => floatval($discount_total),
            'couponCode' => isset($data['couponCode']) ? $data['couponCode'] : '',
            'currency' => $currency,
            'shippingMethods' => $shipping_methods,
            'paymentMethods' => $payment_methods,
            'executionTime' => microtime(true) - $start_timer,
        ]);
    } catch (Exception $e) {
        return headlesswc_error_response(
            'Wystąpił nieoczekiwany błąd',
            HeadlessWC_Error_Codes::UNEXPECTED_ERROR,
            500
        );
    }
}
