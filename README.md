# roqianjas/doku-laravel

Reusable Laravel-first integration for DOKU Checkout with Laravel-friendly contracts for checkout creation, status sync, and webhook verification.

## What This Package Owns

- DOKU Checkout API request construction and signature generation.
- Non-SNAP status check request construction.
- Incoming webhook signature verification.
- Driver switch between `fake` and real `checkout` modes.
- Status normalization from DOKU provider states into Laravel app-friendly states.

## What The Host App Still Owns

- Order and payment persistence.
- Route registration for checkout return pages and webhook endpoints.
- CSRF exemption for the webhook route.
- Applying verified webhook payloads into your domain models.
- Trusting proxy headers when the app runs behind public tunnels or reverse proxies.

## Install In Another Laravel App

Add the package with Composer, then publish config.

```bash
composer require roqianjas/doku-laravel
php artisan vendor:publish --tag=doku-config
```

If you are still developing locally from a monorepo or path repository, keep the current path repository setup and require `roqianjas/doku-laravel` until the package is moved to its own repository.

## Minimal Config

```env
DOKU_DRIVER=checkout
DOKU_ENV=sandbox
DOKU_CLIENT_ID=your-client-id
DOKU_SECRET_KEY=your-secret-key
DOKU_BASE_URL=https://api-sandbox.doku.com
DOKU_NOTIFICATION_URL=https://your-domain/webhooks/doku
DOKU_PAYMENT_DUE_DATE=60
DOKU_AUTO_REDIRECT=true
DOKU_PAYMENT_METHOD_TYPES=VIRTUAL_ACCOUNT_BRI
```

## Core Services

Resolve these contracts from the Laravel container:

- `DokuLaravel\Contracts\CheckoutService`
- `DokuLaravel\Contracts\StatusService`
- `DokuLaravel\Contracts\WebhookVerifier`

Typical host-app usage:

1. Build `CreateCheckoutData` and call `CheckoutService::createCheckout()`.
2. Save checkout metadata into your local payment record.
3. Accept `POST /webhooks/doku` in your host app.
4. Pass headers, raw body, and request path into `WebhookVerifier::parseAndVerify()`.
5. Apply the normalized status to your own payment aggregate.
6. Offer `StatusService::checkStatus()` as a manual fallback for delayed notifications.

## Security Notes

- Exempt only the exact webhook route from Laravel CSRF protection.
- Keep webhook routes POST-only.
- Always verify DOKU signature before updating local payment state.
- Match incoming `Client-Id` with your configured `DOKU_CLIENT_ID`.
- Treat DOKU notifications as retryable and idempotent.
- Never expose `DOKU_SECRET_KEY` in browser code, screenshots, or client logs.

## Package-local Testing

Once this package lives in its own repository:

```bash
composer install
composer test
```

This repository now includes:

- `phpunit.xml.dist`
- package-local unit tests under `tests/Unit`
- a lightweight manual test flow via `composer install` and `composer test`

## Tunnel And Local Testing Notes

When testing through `localhost.run` or a similar reverse proxy:

- point `APP_URL` and `DOKU_NOTIFICATION_URL` to the active tunnel domain,
- trust forwarded proxy headers in the host app,
- use built assets via `npm run build`,
- do not rely on `npm run dev` for public tunnel testing,
- keep the tunnel process alive during the payment flow.

## Extraction Notes

From the demo app monorepo, you can export this package into a standalone folder with:

```powershell
.\scripts\export-doku-package.ps1
```

Before publishing this package publicly:

1. Confirm `roqianjas/doku-laravel` is the final Composer package name you want to publish.
2. Move `packages/doku-laravel` into its own repository root.
3. Copy this README and a license file into that repository.
4. Use manual package verification with `composer install` and `composer test`.
5. Replace path repository usage in host apps with normal Composer installation.
