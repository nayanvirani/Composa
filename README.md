# Composa

Step-by-step product configurators for Shopify. Merchants turn any product into a guided flow
built on their existing variants, with conditional rules and exact pricing. The configured price
is signed by the server and verified at checkout.

## What's in this repo

| Part | Where | Stack |
|---|---|---|
| Merchant app (embedded in Shopify admin) | `resources/js`, `app/Http/Controllers/App`, `routes/app.php` | Laravel 13, Inertia 2, React 18, Polaris |
| Rules & pricing engine (twin) | `engine/js/src`, `app/Engine` | Plain JS + PHP, shared fixtures in `tests/engine` |
| Storefront configurator | `storefront/src` → `extensions/composa-theme/assets` | Preact, esbuild |
| Theme app extension | `extensions/composa-theme` | App embed + Customize button block |
| Checkout pricing | `extensions/composa-cart-transform`, `extensions/composa-price-fallback` | Shopify Functions (JS → Wasm) |
| App Proxy API | `app/Http/Controllers/Proxy`, `routes/proxy.php` | `/apps/composa/*` → `/proxy/*` |
| Super admin | `app/Filament` | Filament 5, spatie/laravel-permission, Google sign-in |
| Public website | `resources/views/site`, `routes/site.php` | Blade, fully managed from super admin |

## Requirements

- PHP 8.4 or newer (8.5 recommended) with `intl`, `pdo_pgsql`, `gd`, `bcmath`, `zip`, `pcntl`
- PostgreSQL 15+ (JSONB), Redis 7+
- Node 22+ for builds, Shopify CLI for extensions

## Local setup

```bash
cp .env.example .env            # fill in database, Redis and Shopify values
composer install
php artisan key:generate
npm install
npm run build                   # merchant app + storefront bundles
php artisan migrate --seed      # plans, roles, templates and website content
```

The first super admin is created by the seeder from `ADMIN_OWNER_EMAIL` (plus
`ADMIN_OWNER_PASSWORD`, or Google sign-in with `ADMIN_GOOGLE_SSO=true`). Sign in at `/admin`.

## Tests

```bash
vendor/bin/phpunit --testsuite Unit   # engine parity, signatures, plan logic
npm test                              # JS engine, cart transform, discount fallback
```

The PHP and JS engines must agree on every fixture in `tests/engine/fixtures`. After changing
either engine, regenerate the snapshot with `npm run snapshot:engine` and run both suites.

## Shopify app

1. Create the app in the Partner Dashboard and set up plans in **App Pricing** (managed pricing).
   Plan handles must match the `plans.handle` column: `free-dev`, `starter`, `growth`, `pro`.
2. Replace the placeholders in `shopify.app.toml`, then `shopify app config link`.
3. `npm run build:storefront && shopify app deploy` publishes the theme extension and both
   functions.

Billing goes through Shopify only: prices and trials are read from Shopify and shown read-only
in super admin. Super admin controls each plan's limits and features.

### Cart modes

| Mode | When | How |
|---|---|---|
| `update` | Shopify Plus and development stores | Cart Transform `lineUpdate` sets the signed price |
| `expand` | All other plans | `lineExpand` into the line's own variant (+ linked add-ons) |
| `discount` | Another app owns the cart transform | Automatic app discount lowers the listed price |

## Deploying to Railway

One Docker image serves three services; `ROLE` picks what each one runs.

| Service | `ROLE` | Notes |
|---|---|---|
| web | `web` | Public domain. Health check path `/up`. Set `RUN_MIGRATIONS=true` on this service only. |
| worker | `worker` | Horizon queues |
| scheduler | `scheduler` | `schedule:work` (rollups, cleanup, emails, downgrade grace) |

Add the PostgreSQL and Redis plugins and a Storage Bucket. Then set the variables from
`.env.example` on every service (Railway shared variables work well). Website media uploaded in
super admin uses `COMPOSA_MEDIA_DISK`: attach a volume at `storage/app/public` for `public`, or
point it at an S3 disk.

## Project rules

- Money is always integer cents, rounded half up.
- Published configurator versions are immutable; edits create a new draft.
- No hidden products or variants are ever created for pricing.
- The storefront embed stays under 10 KB; the UI bundle under 60 KB gzipped (enforced by the build).
