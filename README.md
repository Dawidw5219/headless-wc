# HeadlessWC: Ultimate eCommerce Decoupler

Custom WooCommerce endpoints for headless checkout with enhanced order tracking capabilities.

## New Features

### ✅ Order Key Integration

All redirects now include order parameters for secure order tracking:

- `order` - Order ID
- `key` - WooCommerce order key for verification

### ✅ Order Details API

New endpoint to fetch complete order information using order ID and key.

## API Endpoints

### 1. Create Cart

`POST /wp-json/headless-wc/v1/cart`

### 2. Create Order

`POST /wp-json/headless-wc/v1/order`

- Returns `paymentUrl` for payment processing
- Automatically redirects to `redirectUrl` with order parameters after payment

### 3. Get Order Details (NEW)

`GET /wp-json/headless-wc/v1/order/{order_id}?key={order_key}`

**Parameters:**

- `order_id` (path) - The order ID
- `key` (query) - Order key for verification (format: `wc_order_xyz...`)

**Response:**

```json
{
  "success": true,
  "order": {
    "id": 1180,
    "order_key": "wc_order_Ugdf2Px7xD4m",
    "status": "processing",
    "currency": "PLN",
    "total": 89.99,
    "items": [...],
    "billing": {...},
    "shipping": {...},
    "custom_fields": {...}
  }
}
```

### 4. Get Products

`GET /wp-json/headless-wc/v1/products`

### 5. Get Single Product

`GET /wp-json/headless-wc/v1/products/{slug}`

## Payment Flow

1. **Create Order**: `POST /order` → Returns `paymentUrl`
2. **Payment Page**: Customer is redirected to WooCommerce payment page
3. **Auto-redirect**: After payment, customer is automatically redirected to `redirectUrl` with order parameters:
   ```
   https://yourapp.com/success?order=1180&key=wc_order_Ugdf2Px7xD4m
   ```
4. **Fetch Details**: Use the order ID and key to fetch complete order details

## COD (Cash on Delivery) Support

- ✅ Automatic order confirmation
- ✅ JavaScript-based payment flow
- ✅ Seamless redirect after confirmation
- ✅ Works with all offline payment methods

## Security

- Order details require both order ID and order key for access
- Order key acts as a secure token to prevent unauthorized access
- Compatible with WooCommerce's built-in security model

[WordPress Plugin Site](https://wordpress.org/plugins/headless-wc/)

[NPM Client Package](https://www.npmjs.com/package/headless-wc-client)

[API Documentation](https://dawidw5219.github.io/headless-wc/)
