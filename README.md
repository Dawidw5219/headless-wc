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

### ✅ WooCommerce Integration

**NEW: Settings integrated into WooCommerce admin panel**

- **Native WooCommerce Settings**: HeadlessWC tab in WooCommerce → Settings
- **Configurable options**: Control COD auto-confirmation and order key inclusion
- **Security settings**: Domain whitelist management
- **Status information**: Plugin version and API endpoint URLs

### ✅ Centralized API Security

**NEW: Automatic security for all endpoints** - No manual checks required!

- **Centralized domain whitelist**: Automatic check for all HeadlessWC endpoints
- **WooCommerce requirement check**: Ensures WooCommerce is active before API access
- **API access logging**: Optional security monitoring for all endpoint access
- **Future-proof**: New endpoints are automatically secured without additional code

## Admin Settings

Navigate to **WooCommerce → Settings → HeadlessWC** to configure:

### Security Settings

- **Domain Whitelist**: Restrict API access to specific domains
- **API Access Control**: Secure your headless endpoints
- **API Access Logging**: Monitor all API requests for security

### COD Settings

- **Auto-confirm COD Orders**: Enable/disable automatic COD order confirmation
- **Instant Redirects**: Control immediate redirects for COD payments

### API Settings

- **Include Order Key**: Control whether order keys are added to redirect URLs
- **Secure Order Verification**: Enable order ID + key parameter inclusion

### Status & Information

- **Plugin Version**: Current HeadlessWC version display
- **API Base URL**: Your HeadlessWC API endpoint base URL

## Security Architecture

### Centralized Protection

All HeadlessWC API endpoints (`/wp-json/headless-wc/v1/*`) are automatically protected by:

1. **Domain whitelist check** - Only allowed domains can access the API
2. **WooCommerce availability check** - Ensures WooCommerce is active
3. **Optional access logging** - Track all API requests for monitoring

### Benefits

- **No manual security code** needed in individual endpoints
- **Automatic protection** for new endpoints
- **Consistent security policy** across all API routes
- **Easy monitoring** with optional logging

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
- ✅ **Configurable auto-confirmation** - Control via WooCommerce settings
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

## Installation & Setup

1. **Install the plugin** in your WordPress/WooCommerce site
2. **Configure settings** in WooCommerce → Settings → HeadlessWC
3. **Set domain whitelist** for security (optional)
4. **Enable COD auto-confirmation** for instant redirects
5. **Enable API logging** for security monitoring (optional)
6. **Use API endpoints** in your headless frontend

## Security

- Order details require both order ID and order key for access
- Order key acts as a secure token to prevent unauthorized access
- Compatible with WooCommerce's built-in security model

[WordPress Plugin Site](https://wordpress.org/plugins/headless-wc/)

[NPM Client Package](https://www.npmjs.com/package/headless-wc-client)

[API Documentation](https://dawidw5219.github.io/headless-wc/)
