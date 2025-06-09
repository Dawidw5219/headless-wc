# HeadlessWC: Ultimate eCommerce Decoupler

HeadlessWC transforms your eCommerce approach by providing custom eCommerce endpoints for a headless checkout experience. This revolutionary plugin is designed to cater to the evolving needs of online stores that seek agility, speed, and an enhanced user experience without the constraints of traditional eCommerce platforms.

## Key Features

### ✅ Fast & Secure COD Processing

- **Instant PHP-based redirects** - No JavaScript delays (< 100ms)
- **Automatic order confirmation** - COD orders instantly move to "processing"
- **Secure order keys** - All redirects include order ID + verification key

### ✅ Centralized API Security

- **Automatic endpoint protection** - All `/wp-json/headless-wc/v1/*` routes secured
- **Domain whitelist control** - Restrict access to trusted frontend domains
- **Future-proof security** - New endpoints automatically inherit protection

### ✅ Cache Revalidation System

- **Automatic cache invalidation** - Frontend automatically notified of product changes
- **Perfect for Next.js ISR** - Works with Incremental Static Regeneration
- **Asynchronous requests** - Non-blocking product updates
- **Configurable endpoint** - Set your own revalidation URL
- **Error monitoring** - WordPress admin alerts for failed revalidations

### ✅ Simple Administration

- **WooCommerce submenu integration** - Native admin experience
- **Minimal configuration** - Only essential settings exposed
- **Always-on features** - COD auto-confirmation and order keys enabled by default

## Admin Settings

Navigate to **WooCommerce → HeadlessWC** to configure:

### Security Settings

- **Domain Whitelist**: Restrict API access to specific domains (optional)

### Cache & Performance Settings

- **Cache Revalidation URL**: Automatic frontend cache invalidation on product updates

_Note: COD auto-confirmation and order key inclusion are always enabled for optimal security and performance._

## Cache Revalidation

When you update any product in WooCommerce, HeadlessWC can automatically notify your frontend to refresh its cache.

### How it works:

1. Product gets updated in WooCommerce admin
2. HeadlessWC sends a GET request to your configured URL
3. Your frontend receives the request and revalidates the specific product

### URL Format:

```
GET https://yourapp.com/api/revalidate?slug=product-slug&id=123&action=revalidate&type=product
```

### Error Monitoring:

- **WordPress Admin Alerts** - Get notified immediately when revalidation fails
- **Automatic error cleanup** - Errors older than 24 hours are automatically dismissed
- **Detailed error info** - See exactly what went wrong with HTTP status codes
- **One-click settings access** - Fix configuration directly from error alerts

### Perfect for:

- **Next.js ISR** - Use with `revalidateTag()` or `revalidatePath()`
- **Gatsby** - Trigger incremental builds
- **Custom cache systems** - Redis, Memcached invalidation
- **CDN purging** - CloudFlare, AWS CloudFront cache busting

### Example Next.js API Route:

```javascript
// pages/api/revalidate.js
export default async function handler(req, res) {
  const { slug, id } = req.query;

  try {
    await res.revalidate(`/products/${slug}`);
    return res.json({ revalidated: true, product: id });
  } catch (err) {
    return res.status(500).send("Error revalidating");
  }
}
```

### Error Alert Example:

When your revalidation endpoint fails, you'll see an admin notice like this:

```
🚨 HeadlessWC Cache Revalidation Error

Product: Example Product (ID: 123)
Error: HTTP Error 500
Time: 2024-01-15 14:30:25
URL: https://yourapp.com/api/revalidate?slug=example-product&id=123

Details: Internal Server Error

Your frontend cache revalidation is not working. Please check your revalidation endpoint.
[Go to HeadlessWC Settings]
```

## API Endpoints

### 1. Create Cart

`POST /wp-json/headless-wc/v1/cart`

### 2. Create Order

`POST /wp-json/headless-wc/v1/order`

- Returns `paymentUrl` for payment processing
- Automatically redirects to `redirectUrl` with order parameters after payment

### 3. Get Order Details

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

- ✅ **Instant server-side processing** - No page loading delays
- ✅ **Always enabled** - No configuration needed
- ✅ **Automatic order confirmation** - Orders instantly move to "processing" status
- ✅ **Secure redirects** - Order keys always included for verification
- ✅ **Works with all offline payment methods**

## Security Features

- **Centralized protection** for all HeadlessWC endpoints
- **Domain whitelist** to restrict API access
- **Order verification** with secure order keys
- **WooCommerce dependency check** before API access

## Installation & Setup

1. **Install the plugin** in your WordPress/WooCommerce site
2. **Configure domain whitelist** in WooCommerce → HeadlessWC (optional)
3. **Set cache revalidation URL** for automatic frontend updates (optional)
4. **Use API endpoints** in your headless frontend

_That's it! COD processing and order keys work automatically._

[WordPress Plugin Site](https://wordpress.org/plugins/headless-wc/)
[NPM Client Package](https://www.npmjs.com/package/headless-wc-client)
[API Documentation](https://dawidw5219.github.io/headless-wc/)
