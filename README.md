# Prinx Electronics Store

A production-oriented Laravel ecommerce and service-booking platform for **Prinx Electronics**. The storefront uses the supplied Prinx logo and a clean orange, black and white visual system. It includes a complete administration panel, inventory and order management, product/service publishing, SEO foundations, an XML product feed, and direct **Google Merchant API v1** synchronization.

## Technology

- Laravel 13 / PHP 8.3+
- Vue 3 Composition API + Inertia.js with server-side rendering (SSR)
- Tailwind CSS
- MySQL 8+
- Database-backed queues, cache and sessions
- Google Merchant API v1 using a service account

## Included features

### Storefront

- Responsive home, catalogue, product and service pages
- Search, category, brand, stock and price filtering
- Product image galleries and sale pricing
- Session cart and guest checkout
- Inventory-safe checkout with transactional stock locking
- Cash on delivery, mobile money and bank transfer order methods
- Customer registration and authentication
- Service enquiry forms
- Shipping, returns, privacy, terms, about and contact pages
- XML sitemap, Google-compatible XML product feed and product structured data
- Server-rendered public pages with canonical, robots, Open Graph and Twitter metadata
- Product, service, breadcrumb, store and website JSON-LD structured data

### Admin panel

- Dashboard with sales, stock, order and service-request summaries
- Product CRUD with images, galleries, stock, sale windows, SEO fields and Google attributes
- Service CRUD and customer enquiry workflow
- Category and brand management
- Order and payment-status management
- Google Merchant connection test, queued bulk sync, individual sync and sync logs
- Administrator-only middleware

### Google Merchant integration

- Direct product insertion/update through Merchant API v1
- Product deletion through Merchant API v1
- Service-account OAuth authentication
- Queue jobs for large catalogue syncs
- Local sync status and full sync log history
- Supplemental `/feeds/google/products.xml` feed for diagnostics or feed-based import

## Local setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

For local frontend development:

```bash
npm run dev
```

Run the SSR server in another process for server-rendered public pages:

```bash
npm run start:ssr
```

Or start Laravel, the queue worker, logs and Vite together with:

```bash
composer dev
```

The application is configured for MySQL by default. Create the database named in `.env` before migrating.

### Seeded admin account

After `php artisan migrate --seed`:

- Email: `admin@prinx.co.tz`
- Password: `ChangeMe123!`

**Change this password immediately in production.**

## Product image storage

Uploaded images are stored on the public filesystem. Run:

```bash
php artisan storage:link
```

The admin product form accepts multiple product images. The first image becomes the main image and the remaining images form the gallery.

## Google Merchant Center configuration

### 1. Merchant Center

1. Create or use a Google Merchant Center account.
2. Complete business verification and website claim in Merchant Center.
3. Ensure the website URL in Merchant Center exactly matches `APP_URL`.
4. Configure shipping and returns in Merchant Center to match the store policies.

### 2. Google Cloud service account

1. Create a Google Cloud project.
2. Enable the **Merchant API**.
3. Create a service account and download its JSON key.
4. In Merchant Center, add the service account email as a user with appropriate product-management permission.
5. Upload the JSON key to the server at:

```text
storage/app/private/google/service-account.json
```

Do not commit this file. It is ignored by Git.

### 3. Environment variables

Set:

```env
GOOGLE_MERCHANT_ENABLED=true
GOOGLE_MERCHANT_ACCOUNT_ID=123456789
GOOGLE_MERCHANT_DATA_SOURCE_ID=1234567890
GOOGLE_MERCHANT_CONTENT_LANGUAGE=en
GOOGLE_MERCHANT_FEED_LABEL=TZ
GOOGLE_MERCHANT_CURRENCY=TZS
GOOGLE_MERCHANT_COUNTRY=TZ
GOOGLE_MERCHANT_SHIPPING_SERVICE="Standard delivery"
GOOGLE_MERCHANT_DEFAULT_SHIPPING_PRICE=0
GOOGLE_MERCHANT_CREDENTIALS_PATH=storage/app/private/google/service-account.json
```

`GOOGLE_MERCHANT_DATA_SOURCE_ID` is the numeric product data-source ID from Merchant Center. Products are inserted against this source.

### 4. Test the connection

Sign in as an administrator and open:

```text
/admin/merchant
```

Use **Test Connection**. The application requests a Merchant API access token and attempts to read the configured account.

### 5. Sync the catalogue

On the same admin page:

- **Queue catalog sync** dispatches every active product to the queue.
- Product create/update operations automatically queue a product sync when Merchant synchronization is enabled.
- Product deletion queues a Merchant deletion before soft-deleting the local record.

Keep a queue worker running:

```bash
php artisan queue:work --sleep=2 --tries=3 --timeout=120
```

Example Supervisor config is in `deploy/supervisor.conf.example`.

## SEO and organic visibility

The project includes:

- Public-page SSR via the Inertia SSR process
- Canonical URLs and page-specific meta descriptions
- Open Graph and Twitter cards
- Product and service schema markup
- Breadcrumb schema
- Organization/store schema
- `sitemap.xml`
- `robots.txt`
- Product image alt text
- Merchant attributes for price, availability, condition, brand and GTIN/MPN

For production, make sure:

- `APP_URL=https://your-real-domain.tld`
- HTTPS is active
- Nginx forwards PHP requests to PHP-FPM
- `php artisan storage:link` has been run
- `npm run build` has been run
- `npm run start:ssr` is supervised as a long-running process
- a queue worker is supervised
- Search Console and Merchant Center use the same canonical domain

Example Nginx and Supervisor files are in `deploy/`.

## Deployment checklist

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan optimize
npm ci
npm run build
```

Then restart:

- PHP-FPM
- Laravel queue workers
- Inertia SSR (`php artisan inertia:start-ssr`)

On subsequent deployments:

```bash
php artisan optimize:clear
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

## Test suite

```bash
php artisan test
```

The repository includes checkout, storefront and admin-access feature tests.
