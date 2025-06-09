# HeadlessWC: Ultimate eCommerce Decoupler

Custom WooCommerce endpoints for headless checkout with enhanced order tracking capabilities.

## New Features

### ✅ Order Key Integration

All redirects now include order parameters for secure order tracking:

- `order` - Order ID
- `key` - WooCommerce order key for verification

### ✅ Order Details API

New endpoint to fetch complete order information using order ID and key.

### ✅ Improved COD (Cash on Delivery) Handling

**NEW: PHP-based COD processing** - No more JavaScript delays!

- **Instant redirect**: COD orders are processed immediately at the server level
- **No page loading delays**: Users are redirected before any page content is rendered
- **Automatic order confirmation**: COD orders are automatically set to "processing" status
- **URL encoding fix**: Proper URL parameter encoding (no more `&amp;` issues)

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

- ✅ **NEW: PHP-based processing** - Instant server-side order confirmation
- ✅ **Early redirect hook** - Intercepts before page rendering
- ✅ **Automatic status update** - Orders move to "processing" immediately
- ✅ **Fixed URL encoding** - Proper `&` characters in redirect URLs
- ✅ **Zero JavaScript dependencies** - Pure server-side implementation
- ✅ **Works with all offline payment methods**

### Performance Improvements

**Before (JavaScript-based):**

1. Page loads → DOM ready → Find buttons → Click → Wait → Redirect
2. **Time**: 1-3 seconds

**Now (PHP-based):**

1. Direct server redirect → Immediate
2. **Time**: <100ms

## Security

- Order details require both order ID and order key for access
- Order key acts as a secure token to prevent unauthorized access
- Compatible with WooCommerce's built-in security model

[WordPress Plugin Site](https://wordpress.org/plugins/headless-wc/)

[NPM Client Package](https://www.npmjs.com/package/headless-wc-client)

[API Documentation](https://dawidw5219.github.io/headless-wc/)
