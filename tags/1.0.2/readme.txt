=== HeadlessWC: Ultimate eCommerce Decoupler ===
Contributors: dawidw11
Tags: woocommerce, headless, decoupled, cart, rest-api
Requires at least: 5.1
Tested up to: 6.4.3
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Stable tag: 1.0.2

The ultimate solution for integrating headless checkout functionalities into your WC store

== Description ==
HeadlessWC transforms your eCommerce approach by providing custom eCommerce endpoints for a headless checkout experience. This revolutionary plugin is designed to cater to the evolving needs of online stores that seek agility, speed, and an enhanced user experience without the constraints of traditional eCommerce platforms.

[API Documentation](https://dawidw5219.github.io/headless-wc/)

## Cart Endpoint

This endpoint is designed to manage the shopping cart for a WC store via a WordPress plugin. It allows for adding products to the cart, applying a coupon code, and retrieving cart details including products, totals, available shipping, and payment methods.

**Request**

- **URL**: `/wp-json/headless-wc/v1/cart`
- **Type**: `POST`
- **Content-Type**: `application/json`
- **Body**:
  - `cart`: An array of objects where each object represents a product. Each product object must have:
    - `id` (required): The product ID.
    - `quantity` (optional, default is 1): The quantity of the product.
  - `couponCode` (optional): A string representing a coupon code to apply to the cart.

**Response**

- **Success Response Code**: `200 OK`
- **Error Response Code**: `500 Internal Server Error` (In case of an unexpected error)
- **Body**:
  - `success`: A boolean indicating if the request was successful.
  - `products`: An array of objects representing the products in the cart, including details such as product ID, name, image URLs, quantity, price, sale information, and totals.
  - `subtotal`: The cart subtotal before taxes, shipping, and discounts.
  - `total`: The final total of the cart after all taxes, shipping, and discounts.
  - `taxTotal`: The total amount of taxes.
  - `shippingTotal`: The total shipping cost.
  - `discountTotal`: The total discount amount applied from coupons.
  - `couponCode`: The coupon code applied, if any.
  - `currency`: The currency used for the cart totals.
  - `availableShippingMethods`: An array of available shipping methods, including details such as method name, ID, price, tax, and zone information.
  - `availablePaymentMethods`: An array of available payment methods, including details such as method ID, title, and description.

**Notes**

- The cart will be emptied before adding the new items specified in the request.
- If a product ID or quantity is invalid, it will be skipped.
- The currency and totals are retrieved based on the WC settings.
- Shipping and payment methods are determined based on the store's configuration and the current state of the cart.

## Order Endpoint

This endpoint is designed to create and process a new order in a WC store through a WordPress plugin. It involves order creation, applying shipping methods, coupons, and setting payment methods based on the provided request data.

**Request**

- **Type**: `POST`
- **URL**: `/wp-json/headless-wc/v1/order`
- **Content-Type**: `application/json`
- **Body Parameters**:
  - `cart`: An array of objects, each representing a product to be added to the order. Each product object should include:
    - `id` (required): The product ID.
    - `quantity` (optional, default is 1): The quantity of the product.
  - `furgonetkaPoint` (optional): A custom field for specifying a pickup point.
  - `furgonetkaPointName` (optional): A name for the specified pickup point.
  - `redirect_url` (required): A URL to redirect the client after payment is made.
  - `shipping_method_id` (required): The ID of the shipping method to apply to the order.
  - `coupon_code` (optional): A coupon code to apply to the order.
  - `payment_method_id` (required): The ID of the payment method for the order.
  - `total` (required): The total amount the client expects to pay.
  - `use_different_shipping` (optional): A flag to indicate whether a different shipping address should be used.

**Response**

- **Success Response Code**: `200 OK`
- **Error Response Codes**:
  - `400 Bad Request` for missing or invalid data such as no valid redirect URL, invalid products, invalid shipping method, invalid payment method, or total mismatch.
  - `500 Internal Server Error` for unexpected errors.
- **Body**:
  - `success`: A boolean indicating the request was successful.
  - `orderId`: The ID of the created order.
  - `paymentUrl`: A URL to where the client should be redirected to make the payment.

**Notes**

- Orders are initially created with a `pending` status and marked as having accepted terms.
- The `redirect_url` is essential for redirecting clients after payment.
- Valid products must be specified in the `cart` array to proceed with the order creation.
- Shipping and billing addresses are set based on the provided data, with the option to specify different shipping information.
- The endpoint validates the shipping method, applies any provided coupon code, and sets the specified payment method.
- The order's total is verified against the client's expected total to prevent mismatches.
- If any validation fails (such as invalid shipping method, payment method, or total mismatch), the created order is deleted to maintain data integrity.

== Installation ==

1. Ensure WC is installed and activated on your WordPress site.
2. Upload the `HeadlessWC` plugin to your `/wp-content/plugins/` directory, or install it directly through the WordPress plugins screen.
3. Activate the plugin through the \'Plugins\' menu in WordPress.
4. Enjoy the cutting-edge features and enhancements it brings to your WC store!

== Frequently Asked Questions ==

= Do I need technical expertise to use this plugin? =

While Headless WC is built with simplicity in mind, basic knowledge of headless architecture will help you maximize its potential.

= Can I use this plugin with any theme? =
Absolutely! Our plugin is designed to work seamlessly with any theme, offering you complete freedom in designing your store\'s front end.

= Is Headless WC compatible with the latest version of Woo? =
Yes, we are committed to keeping our plugin updated with the latest WC releases to ensure compatibility and performance.

== Changelog ==

= v1.0.0 =

Initial version