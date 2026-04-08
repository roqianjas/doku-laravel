# Panduan Integrasi `roqianjas/doku-laravel`

Panduan ini ditujukan untuk host app Laravel yang ingin memakai package `roqianjas/doku-laravel` sebagai integration layer ke DOKU Checkout.

## 1. Scope Package

Package ini menangani:

- pembuatan request DOKU Checkout,
- signature generation untuk request checkout dan status,
- verifikasi signature webhook DOKU non-SNAP,
- normalisasi status provider ke status internal Laravel-friendly,
- driver switch antara `fake` dan `checkout`.

Package ini tidak menangani:

- penyimpanan order dan payment di database host app,
- route return page, webhook, dan halaman sandbox fake,
- update state domain model Anda,
- idempotency policy di level business,
- queueing atau retry policy internal aplikasi Anda.

## 2. Requirement

- PHP `^8.3`
- Laravel host app yang kompatibel dengan komponen `illuminate/*` versi `^13.0`
- credential DOKU Checkout sandbox atau production

Install package:

```bash
composer require roqianjas/doku-laravel:^0.1
php artisan vendor:publish --tag=doku-config
```

## 3. Konfigurasi Environment

Minimal `.env` untuk sandbox:

```env
DOKU_DRIVER=checkout
DOKU_ENV=sandbox
DOKU_CLIENT_ID=your-client-id
DOKU_SECRET_KEY=your-secret-key
DOKU_BASE_URL=https://api-sandbox.doku.com
DOKU_NOTIFICATION_URL=https://your-domain/webhooks/doku
DOKU_PAYMENT_DUE_DATE=60
DOKU_AUTO_REDIRECT=true
DOKU_REQUEST_TIMEOUT=20
DOKU_PAYMENT_METHOD_TYPES=VIRTUAL_ACCOUNT_BRI
```

Catatan:

- `DOKU_BASE_URL` boleh dikosongkan. Package akan infer otomatis ke sandbox atau production.
- `DOKU_NOTIFICATION_URL` dipakai sebagai default notification URL ketika Anda tidak override per transaksi.
- `DOKU_MERCHANT_ID` masih reserved dan belum dipakai oleh flow package saat ini.
- `DOKU_VALIDATE_RESPONSE_SIGNATURE` sudah ada di config, tetapi saat ini belum dipakai oleh service HTTP package.

## 4. Service Yang Dipakai Host App

Package mengekspos 3 contract utama dari container Laravel:

- `DokuLaravel\Contracts\CheckoutService`
- `DokuLaravel\Contracts\StatusService`
- `DokuLaravel\Contracts\WebhookVerifier`

DTO utama:

- `DokuLaravel\DTO\CreateCheckoutData`
- `DokuLaravel\DTO\CheckoutResult`
- `DokuLaravel\DTO\StatusResult`
- `DokuLaravel\DTO\NotificationData`

## 5. Flow Integrasi Yang Direkomendasikan

### 5.1 Create checkout

Flow umumnya:

1. Host app membuat record order internal.
2. Host app memanggil `CheckoutService::createCheckout()`.
3. Host app menyimpan hasil checkout ke tabel payment internal.
4. User diarahkan ke `paymentUrl` dari `CheckoutResult`.

Contoh controller:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use DokuLaravel\Contracts\CheckoutService;
use DokuLaravel\DTO\CreateCheckoutData;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function store(Request $request, CheckoutService $checkoutService)
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_email' => ['required', 'email', 'max:120'],
            'amount' => ['required', 'integer', 'min:10000'],
        ]);

        $order = Order::create([
            'order_number' => 'ORD-'.now()->format('YmdHis'),
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'amount' => (int) $validated['amount'],
            'currency' => 'IDR',
            'status' => 'created',
            'line_items' => [[
                'id' => 'SKU-1',
                'name' => 'Starter Pack',
                'price' => (int) $validated['amount'],
                'quantity' => 1,
            ]],
        ]);

        $checkout = $checkoutService->createCheckout(new CreateCheckoutData(
            orderNumber: $order->order_number,
            amount: $order->amount,
            currency: $order->currency,
            customerName: $order->customer_name,
            customerEmail: $order->customer_email,
            callbackUrl: route('payments.return', $order->order_number),
            callbackUrlResult: route('payments.return', $order->order_number),
            notificationUrl: config('doku.notification_url'),
            paymentDueDate: (int) config('doku.payment_due_date', 60),
            autoRedirect: (bool) config('doku.auto_redirect', true),
            lineItems: $order->line_items ?? [],
        ));

        $order->payments()->create([
            'provider' => 'doku',
            'provider_reference' => $checkout->providerReference,
            'request_id' => $checkout->requestId,
            'amount' => $order->amount,
            'currency' => $order->currency,
            'status' => $checkout->status,
            'checkout_url' => $checkout->paymentUrl,
            'raw_response_summary' => $checkout->raw,
        ]);

        return Inertia::location($checkout->paymentUrl);
    }
}
```

### 5.2 Handle webhook

Flow umumnya:

1. DOKU memanggil `POST /webhooks/doku`.
2. Host app mengambil raw body dan headers request.
3. Host app memanggil `WebhookVerifier::parseAndVerify()`.
4. Host app mencari payment berdasarkan `orderNumber`.
5. Host app memastikan proses idempotent.
6. Host app mengubah status payment internal berdasarkan `normalizedStatus`.

Contoh controller:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use DokuLaravel\Contracts\WebhookVerifier;
use DokuLaravel\Exceptions\SignatureVerificationException;
use Illuminate\Http\Request;

class DokuWebhookController extends Controller
{
    public function __invoke(Request $request, WebhookVerifier $webhookVerifier)
    {
        try {
            $notification = $webhookVerifier->parseAndVerify(
                headers: $request->headers->all(),
                body: $request->getContent(),
                requestTarget: $request->getPathInfo(),
            );
        } catch (SignatureVerificationException $exception) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payment = Payment::query()
            ->whereHas('order', fn ($query) => $query->where('order_number', $notification->orderNumber))
            ->latest()
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment not found.'], 202);
        }

        // Terapkan idempotency di sini, misalnya berdasarkan request id webhook.
        // Lalu update status domain model Anda berdasarkan $notification->normalizedStatus.

        return response()->json(['message' => 'Notification received.']);
    }
}
```

### 5.3 Manual status sync

Manual status sync berguna kalau:

- webhook belum masuk,
- Anda ingin recovery transaksi lama,
- Anda ingin tombol admin untuk re-check status ke gateway.

Contoh:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use DokuLaravel\Contracts\StatusService;

class PaymentStatusController extends Controller
{
    public function sync(Payment $payment, StatusService $statusService)
    {
        $status = $statusService->checkStatus($payment->order->order_number);

        // Simpan event audit internal Anda.
        // Terapkan status $status->normalizedStatus ke payment/order internal.

        return back()->with('success', 'Status berhasil disinkronkan.');
    }
}
```

## 6. Route Dan Middleware Yang Perlu Disiapkan

Minimal:

```php
Route::post('/webhooks/doku', DokuWebhookController::class)->name('doku.webhook');
Route::get('/payments/{order}/return', [PaymentController::class, 'showReturn'])->name('payments.return');
```

Hal penting:

- route webhook harus `POST`,
- route webhook harus dikecualikan dari CSRF,
- jika app Anda berada di balik tunnel atau reverse proxy, trust forwarded proxy headers.

Contoh pengecualian CSRF:

```php
$middleware->preventRequestForgery(except: [
    'webhooks/doku',
]);
```

## 7. Contract Dan Field Yang Perlu Anda Simpan

### 7.1 `CreateCheckoutData`

Field paling penting:

- `orderNumber`
- `amount`
- `currency`
- `customerName`
- `customerEmail`
- `callbackUrl`
- `callbackUrlResult`
- `notificationUrl`
- `paymentDueDate`
- `autoRedirect`
- `paymentMethodTypes`
- `lineItems`
- `additionalInfo`

### 7.2 `CheckoutResult`

Field penting untuk persistence:

- `requestId`
- `providerReference`
- `paymentUrl`
- `status`
- `expiresAt`
- `raw`

### 7.3 `StatusResult`

Field penting:

- `requestId`
- `reference`
- `providerStatus`
- `normalizedStatus`
- `paymentMethod`
- `amount`
- `raw`

### 7.4 `NotificationData`

Field penting:

- `verified`
- `clientId`
- `requestId`
- `requestTimestamp`
- `orderNumber`
- `originalRequestId`
- `providerStatus`
- `normalizedStatus`
- `paymentMethod`
- `amount`
- `payload`

## 8. Mapping Status Internal

Mapping bawaan package:

| Provider status | Normalized status |
| --- | --- |
| `SUCCESS` | `paid` |
| `PENDING` | `pending` |
| `REDIRECT` | `pending` |
| `TIMEOUT` | `pending` |
| `FAILED` | `failed` |
| `EXPIRED` | `expired` |
| `CANCELLED` / `CANCELED` | `cancelled` |
| `REFUNDED` | `refunded` |
| lainnya | `unknown` |

## 9. Mode `fake` Untuk Pengembangan Lokal

`DOKU_DRIVER=fake` berguna untuk local demo tanpa credential DOKU asli.

Perlu diperhatikan:

- package hanya menghasilkan `paymentUrl` fake,
- host app tetap harus menyediakan halaman sandbox fake-nya sendiri,
- fake driver tidak menggantikan webhook production flow.

Kalau Anda tidak punya halaman sandbox fake, lebih aman langsung gunakan `DOKU_DRIVER=checkout` dengan sandbox credential DOKU.

## 10. Checklist Production

- gunakan domain publik yang stabil,
- isi `DOKU_NOTIFICATION_URL` dengan endpoint webhook production,
- pastikan secret key tersimpan di server saja,
- verifikasi signature sebelum update status,
- cocokkan `Client-Id` incoming dengan config,
- simpan `request_id` dan `provider_reference` untuk audit,
- buat processing webhook idempotent,
- sediakan manual status sync untuk fallback,
- log request id dan order number untuk debugging,
- uji success, failed, expired, duplicate webhook, dan delayed notification.

## 11. Rekomendasi Struktur Data Host App

Minimal host app menyimpan:

- `orders`
  - `order_number`
  - `customer_name`
  - `customer_email`
  - `amount`
  - `currency`
  - `status`
  - `line_items`
- `payments`
  - `provider`
  - `provider_reference`
  - `request_id`
  - `payment_method`
  - `amount`
  - `currency`
  - `status`
  - `checkout_url`
  - `paid_at`
  - `expired_at`
  - `raw_response_summary`
- `payment_events`
  - `event_type`
  - `source`
  - `provider_request_id`
  - `signature_status`
  - `payload`
  - `processed_at`

## 12. Urutan Implementasi Di Platform Anda

Urutan yang saya sarankan:

1. install package dan publish config,
2. siapkan tabel `orders`, `payments`, dan `payment_events`,
3. implement create checkout,
4. implement return page,
5. implement webhook endpoint,
6. implement idempotent payment status applier,
7. implement manual status sync,
8. uji sandbox end-to-end,
9. baru pindah ke production domain dan credential production.
