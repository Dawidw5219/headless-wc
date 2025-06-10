<?php
if (! defined('ABSPATH')) {
    exit;
}

function headlesswc_handle_order_request(WP_REST_Request $request)
{
    try {
        $data = $request->get_json_params();

        // Sprawdź koszyk PRZED utworzeniem zamówienia
        if (empty($data['cart']) || !is_array($data['cart'])) {
            return headlesswc_error_response(
                'Koszyk jest pusty lub nieprawidłowy',
                HeadlessWC_Error_Codes::CART_EMPTY
            );
        }

        // Waliduj produkty w koszyku PRZED utworzeniem zamówienia (pomijaj niepoprawne)
        $valid_products = headlesswc_validate_cart_products($data['cart']);
        if (empty($valid_products)) {
            return headlesswc_error_response(
                'Nie znaleziono prawidłowych produktów w koszyku. Produkty mogą nie istnieć lub mieć nieprawidłowe ilości.',
                HeadlessWC_Error_Codes::NO_VALID_PRODUCTS
            );
        }

        if (empty($data['redirectUrl'])) {
            return headlesswc_error_response(
                'Adres przekierowania jest wymagany do przetwarzania płatności',
                HeadlessWC_Error_Codes::REDIRECT_URL_REQUIRED
            );
        }

        // Teraz utwórz zamówienie (wszystkie walidacje przeszły)
        $order = wc_create_order();
        $order->update_status('pending');
        update_post_meta($order->get_id(), '_terms_accepted', 'yes');

        // Obsługa customowych pól
        if (! empty($data['customFields']) && is_array($data['customFields'])) {
            foreach ($data['customFields'] as $key => $value) {
                // Walidacja klucza - tylko alfanumeryczne znaki i podkreślenia
                $sanitized_key = sanitize_key($key);
                if (empty($sanitized_key) || strlen($sanitized_key) > 50) {
                    continue;
                }

                // Sanityzacja wartości w zależności od typu
                if (is_string($value)) {
                    $sanitized_value = sanitize_text_field($value);
                } elseif (is_numeric($value)) {
                    $sanitized_value = $value;
                } elseif (is_bool($value)) {
                    $sanitized_value = $value ? 'yes' : 'no';
                } else {
                    // Dla innych typów konwertujemy do stringa i sanityzujemy
                    $sanitized_value = sanitize_text_field(strval($value));
                }

                $order->update_meta_data($sanitized_key, $sanitized_value);
            }
        }

        $order->add_meta_data('headlesswc_redirect_url', $data['redirectUrl'], true);
        $order->save();

        // Dodaj produkty do zamówienia (już zwalidowane)
        headlesswc_apply_cart_products($valid_products, $order);

        $order->set_address(headlesswc_map_customer_data($data), 'billing');
        $order->set_address(! empty($data['useDifferentShipping']) ? headlesswc_map_customer_data($data, true) : headlesswc_map_customer_data($data), 'shipping');

        // Sprawdź czy zamówienie zawiera tylko produkty wirtualne
        $is_virtual_order = headlesswc_is_virtual_order($order);

        // Zastosuj metodę wysyłki tylko jeśli zamówienie nie jest całkowicie wirtualne
        if (!$is_virtual_order) {
            $shipping_method_id = $data['shippingMethodId'] ?? '';
            if (!empty($shipping_method_id)) {
                if (!headlesswc_apply_shipping_method($shipping_method_id, $order)) {
                    return headlesswc_error_response(
                        'Nieprawidłowa metoda wysyłki: ' . $shipping_method_id,
                        HeadlessWC_Error_Codes::INVALID_SHIPPING_METHOD
                    );
                }
            }
            // Jeśli shipping_method_id jest puste dla produktów fizycznych, to jest OK - może być darmowa dostawa
        }

        headlesswc_apply_cupon($data['couponCode'], $order);

        // Payment method - jeśli puste, użyj domyślnego
        $payment_method = sanitize_text_field($data['paymentMethodId'] ?? '');
        if (empty($payment_method)) {
            // Pobierz domyślną metodę płatności
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            if (!empty($available_gateways)) {
                $payment_method = array_key_first($available_gateways);
            } else {
                return headlesswc_error_response(
                    'Brak dostępnych metod płatności',
                    HeadlessWC_Error_Codes::NO_PAYMENT_METHODS
                );
            }
        } else {
            // Sprawdź czy podana metoda płatności istnieje
            if (!array_key_exists($payment_method, WC()->payment_gateways->payment_gateways())) {
                return headlesswc_error_response(
                    'Nieprawidłowa metoda płatności: ' . $payment_method,
                    HeadlessWC_Error_Codes::INVALID_PAYMENT_METHOD
                );
            }
        }

        $order->set_payment_method($payment_method);
        $order->calculate_totals();
        $client_total = floatval($data['total'] ?? '0');
        $server_total = floatval($order->get_total());
        //Total mismatch error
        // if ( abs( $client_total - $server_total ) > 0.01 ) {
        //     if ( isset( $order ) && $order->get_id() > 0 ) {
        //         wp_delete_post( $order->get_id(), true );
        //     }
        //     return new WP_REST_Response( [ 'error' => 'Total mismatch. Client: ' . $client_total . ', Server: ' . $server_total ], 400 );
        // }

        // Generate proper payment URL with required parameters
        $payment_url = $order->get_checkout_payment_url(true);
        $payment_url = add_query_arg('pay_for_order', 'true', $payment_url);

        return headlesswc_success_response([
            'orderId' => $order->get_id(),
            'paymentUrl' => $payment_url,
        ]);
    } catch (Exception $e) {
        return headlesswc_error_response(
            'Wystąpił nieoczekiwany błąd: ' . $e->getMessage(),
            HeadlessWC_Error_Codes::UNEXPECTED_ERROR,
            500
        );
    }
}

function headlesswc_map_customer_data($data, $is_shipping = false)
{
    $prefix = $is_shipping ? 'shipping' : 'billing';
    return [
        'first_name' => sanitize_text_field($data[$prefix . 'FirstName'] ?? ''),
        'last_name' => sanitize_text_field($data[$prefix . 'LastName'] ?? ''),
        'company' => sanitize_text_field($data[$prefix . 'Company'] ?? ''),
        'email' => sanitize_email($data[$prefix . 'Email'] ?? ''),
        'phone' => sanitize_text_field($data[$prefix . 'Phone'] ?? ''),
        'address_1' => sanitize_text_field($data[$prefix . 'Address1'] ?? ''),
        'address_2' => sanitize_text_field($data[$prefix . 'Address2'] ?? ''),
        'city' => sanitize_text_field($data[$prefix . 'City'] ?? ''),
        'state' => sanitize_text_field($data[$prefix . 'State'] ?? ''),
        'postcode' => sanitize_text_field($data[$prefix . 'Postcode'] ?? ''),
        'country' => sanitize_text_field($data[$prefix . 'Country'] ?? ''),
    ];
}


function headlesswc_apply_shipping_method($shipping_method_id, $order)
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

function headlesswc_apply_cupon($couponCode, $order)
{
    if (empty($couponCode) || ! is_string($couponCode)) {
        return false;
    }
    if (! $order->apply_coupon(sanitize_text_field($couponCode))) {
        return false;
    }
    return true;
}

function headlesswc_apply_cart_products($valid_products, $order)
{
    // Produkty są już zwalidowane, więc po prostu je dodaj
    foreach ($valid_products as $product) {
        $product_id = $product['id'];
        $quantity = $product['quantity'];
        $variation_id = isset($product['variation_id']) ? $product['variation_id'] : 0;
        $variation = isset($product['variation']) ? $product['variation'] : [];

        if ($variation_id > 0) {
            $order->add_product(wc_get_product($variation_id), $quantity, $variation);
        } else {
            $order->add_product(wc_get_product($product_id), $quantity);
        }
    }
    return true;
}

function headlesswc_is_virtual_order($order)
{
    $items = $order->get_items();
    if (empty($items)) {
        return false;
    }

    foreach ($items as $item) {
        $product = $item->get_product();
        if (! $product || ! $product->is_virtual()) {
            return false;
        }
    }

    return true;
}
