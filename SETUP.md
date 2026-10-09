# CY-Market — Setup Guide

CY-Market is a single-seller, farm-direct e-commerce pilot for the Tarkwa
mining communities (Ghana), built on Laravel 13 + Filament 3 + Tailwind v4.
This guide gets the freshly-generated Phase 1 build running locally on your
XAMPP machine, against **MySQL**.

## 1. Prerequisites

- PHP 8.3+ (XAMPP's bundled PHP) with these extensions enabled in
  `C:\xampp\php\php.ini` — check each with `php -m` from a terminal:
  - `pdo_mysql` / `mysqli` — required for the MySQL connection.
  - **`intl`** — required by Filament (currency/number/date formatting used
    throughout the admin panel, e.g. every `->money('GHS')` column). This
    one is very commonly disabled by default on XAMPP and will make
    `composer install` fail (see Troubleshooting below) if it's off.
  - `gd` — optional; used only to generate the seeded product placeholder
    images. Seeding still works without it, just without images.
  - `mbstring`, `fileinfo`, `curl` — on by default on XAMPP.

  To enable an extension: open `C:\xampp\php\php.ini`, find its line (e.g.
  `;extension=intl`), remove the leading `;`, save, then close and reopen
  any terminal windows so the change takes effect. Confirm with:
  ```
  php -m | findstr intl
  ```

- Composer 2.x
- Node.js 18+ and npm
- MySQL running (XAMPP's Control Panel — start "MySQL"), with a
  `cymarket` database created. Easiest via phpMyAdmin (http://localhost/phpmyadmin
  → New → name it `cymarket`), or from a terminal:
  ```
  "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS cymarket CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
  ```

## 2. Install dependencies

From the project root (`C:\xampp\htdocs\cy-market`):

```
composer install
npm install
```

`composer.json` now requires `filament/filament`, `spatie/laravel-permission`
and `guzzlehttp/guzzle` in addition to the base Laravel packages, so this
first `composer install` will download noticeably more than the original
skeleton did. The stale `composer.lock` from before those packages were
added has already been removed so Composer resolves everything fresh.

**If this fails with an `ext-intl` / security-advisory error** — see
Troubleshooting at the bottom; it's almost always the `intl` extension
being disabled.

## 3. Environment

Your existing `.env` (and its `APP_KEY`) was kept as-is — a CY-Market
settings block was appended to the bottom of it (delivery fee, MTN MoMo
sandbox mode, SMS log mode, Filament path). `DB_CONNECTION` is set to
`mysql` against the `cymarket` database, `root` user, no password —
XAMPP's defaults. Adjust `DB_USERNAME`/`DB_PASSWORD` in `.env` if your
MySQL root account is configured differently.

## 4. Database: migrate + seed

With MySQL running and the `cymarket` database created:

```
php artisan migrate
php artisan db:seed
php artisan storage:link
```

`db:seed` runs, in order: roles → delivery zones → staff/customer users →
categories → **~46 real farm-produce products** (with generated placeholder
photos) → settings → ~20 sample orders spanning every order status. This is
the seed set the build was asked to prioritise — running it gives you a
fully populated catalogue and admin dashboard immediately, not an empty
database.

If you ever want to start over: `php artisan migrate:fresh --seed`.

## 5. Run it

```
php artisan serve
```

Then in a second terminal (for Tailwind/asset building during development):

```
npm run dev
```

(or `npm run build` for a one-off production build, e.g. before deploying
under XAMPP's Apache instead of `artisan serve`).

- Storefront: http://127.0.0.1:8000
- Admin panel: http://127.0.0.1:8000/admin

## 6. Seeded login credentials

Every seeded account uses the password **`password`**. This is sandbox/pilot
data only — change every one of these before any real deployment.

| Role | Email | Notes |
|---|---|---|
| Super Admin | admin@cymarket.test | Full access: catalogue, orders, users, settings, all reports |
| Inventory Officer | inventory1@cymarket.test | Also: inventory2@cymarket.test |
| Marketing Admin | marketing@cymarket.test | Phase 2 role, scaffolded — no dedicated screens yet |
| Support Agent | support@cymarket.test | Read-only order visibility in the admin panel |
| Customer (x8) | ama.serwaa@example.test, kofi.adjei@example.test, abena.frimpong@example.test, yaw.asante@example.test, adwoa.nyarko@example.test, kwesi.darko@example.test, akua.boadi@example.test, kwame.antwi@example.test | Storefront login, each has a saved Tarkwa-area delivery address |

## 7. Testing checkout end-to-end (zero credentials)

**Pay by transfer is currently the only payment method the storefront
offers.** MTN MoMo integration (`MtnMomoPaymentService`, its webhook, the
simulate routes) is complete and tested but dormant - getting real MTN API
credentials is on hold for now, so checkout never creates a MoMo payment
regardless of what's posted to it. It's a controller change to bring back
later (`App\Http\Controllers\Storefront\CheckoutController::store()`), not
a rebuild - see `PaymentGatewayResolver`.

1. Fill in **Admin > Settings > "Pay by Transfer"**: account name, account
   number, network/bank name. Checkout is blocked with a clear message
   until this is done - there's no fallback method right now.
2. Browse the storefront, add products to the cart, go to Checkout, fill
   in delivery details and submit - you'll land on an instructions page
   showing the account to pay and the order's reference number.
3. In the admin panel (as the Super Admin), open **Sales → Payments** and
   use the **Confirm Payment** row action on that order's pending row
   (optionally recording the amount actually received and a reference
   note). The order is marked Paid, stock is deducted, and an SMS + email
   are "sent" (written to `storage/logs/sms-*.log` since `SMS_MODE=log`,
   and to the log per `MAIL_MAILER=log`). Use **Reject** instead to test
   the payment-failed fallback (order stays Pending Payment, reservation
   released, customer can retry).
4. Open **Sales → Orders** to move the order through Processing →
   Dispatched → Delivered → Completed, or Cancel it. Every transition is
   logged to the order's audit trail and triggers another SMS + email.

Confirming/rejecting is idempotent (replaying it doesn't double-charge
stock or double-send notifications) - covered by
`tests/Feature/ManualPaymentTest.php` and
`tests/Feature/OrderNotificationEmailTest.php`.

### MTN MoMo (dormant) and tracking-update emails

The MTN MoMo gateway, webhook and simulate pages still exist and are still
tested (`tests/Feature/MtnMomoWebhookTest.php`,
`tests/Feature/MtnMomoLiveTest.php`) - they're just not reachable from the
storefront while it's turned off. See "Bringing MTN MoMo back" below.

Every order-lifecycle event sends a matching email (not just SMS) when the
order has an address on file - `MAIL_MAILER=log` (the default) writes
these to the log instead of a real inbox, so this needs no setup either
(`tests/Feature/OrderNotificationEmailTest.php`).

## 8. Going live later

### Bringing MTN MoMo back

The integration itself needs no rebuilding - only `CheckoutController`
needs to offer it again (re-add a `payment_method` field/selector to
`resources/views/storefront/checkout/index.blade.php` and
`CheckoutController::store()`, which currently hardcodes `'manual'`).
Once that's back, flip the mode flags in `.env`:

```
# MTN MoMo (Collection API, Request to Pay). Try MTN's developer sandbox first:
MOMO_MODE=live
MOMO_ENVIRONMENT=sandbox
MOMO_BASE_URL=https://sandbox.momodeveloper.mtn.com
MOMO_CURRENCY=EUR            # sandbox only accepts EUR
MOMO_SUBSCRIPTION_KEY=...    # Collection product key
MOMO_API_USER=...            # UUID created via the sandbox provisioning API
MOMO_API_KEY=...
MOMO_CALLBACK_URL=https://your-real-domain/webhooks/mtn-momo

# Real payments in Ghana: MOMO_ENVIRONMENT=mtnghana,
# MOMO_BASE_URL=https://proxy.momoapi.mtn.com, MOMO_CURRENCY=GHS and the
# credentials issued by MTN Ghana's MoMo business onboarding.
```

### SMS and email

```
SMS_MODE=live
SMS_GATEWAY_BASE_URL=...
SMS_GATEWAY_API_KEY=...
SMS_GATEWAY_CLIENT_ID=...

# Order-tracking-update emails - any Laravel-supported mailer works, no
# app code changes needed:
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
```

`MtnMomoPaymentService` and `SmsNotificationService` both branch on these
config values — the sandbox/log code paths and the live HTTP-call code
paths already exist side by side. Email just uses Laravel's own mail
config (`config/mail.php`), so there's no app-specific "mode" flag for it.

## 9. Scheduled jobs

Three console commands need to run on a schedule (they're already
registered in `routes/console.php` via Laravel's scheduler):

- `cymarket:release-expired-reservations` — every minute (releases soft
  stock holds whose payment window lapsed and cancels the unpaid order)
- `cymarket:mark-abandoned-carts` — hourly
- `cymarket:escalate-stale-orders` — hourly

On Windows/XAMPP there's no cron, so either run `php artisan schedule:work`
in a background terminal during development, or set up a Windows Task
Scheduler entry that runs `php artisan schedule:run` every minute once you
deploy for real.

## 10. Running the automated tests

```
php artisan test
```

or `composer test`. The suite (`tests/Feature/*`) covers: cart add/update/
remove and stock-limit validation, the full guest checkout → sandbox
payment → order-confirmed flow (including duplicate-payment idempotency and
a declined-payment reservation release), the order status state machine
(valid/invalid transitions, cancellation restocking), the stock-reservation
race condition described in the TOR (two customers on the last units — the
second is correctly placed On Hold), the MTN MoMo callback endpoint (success,
failure, unknown reference, replay idempotency), and Filament admin RBAC
gates per role. Tests run against an **in-memory SQLite** database
regardless of your MySQL setup above (`phpunit.xml` hard-codes
`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`), so they never touch your
real `cymarket` database and need no extra setup.

## 11. What's built vs. scaffolded

**Fully built (Phase 1, per the agreed scope):** product catalogue with
categories/seasonality/low-stock, cart, guest + registered checkout,
pay-by-transfer payment flow (MTN MoMo dormant, see §7/§8), SMS + email +
in-app bell notifications, the full order lifecycle with audit trail, soft
stock reservation with race-condition handling, the Filament admin panel
(Products, Categories, Orders, Users, Delivery Zones, Payments, Stock
Movements, Notification Logs, a Settings page, a staff notification bell,
and a KPI/sales/low-stock dashboard), and RBAC for Super Admin, Inventory
Officer and (read-only) Support Agent.

### In-app (bell) notifications

Two one-way alerts, both via Laravel's standard `notifications` table
(`App\Notifications\NewOrderPlaced`, `App\Notifications\OrderStatusUpdated`
- see `OrderService::notify()`/`notifyStaffOfNewOrder()`):

- **Staff** (Super Admin, Inventory Officer) get a bell alert in the admin
  panel the moment any order is placed - the Filament topbar bell, enabled
  via `->databaseNotifications()` in `AdminPanelProvider`. Particularly
  useful for pay-by-transfer orders, since nothing else prompts staff to
  go confirm one.
- **Registered customers** get a bell on the storefront (next to the cart
  pill, top-right once logged in) for every lifecycle event on their own
  orders - same event set as SMS/email. Guests have no account to show a
  bell in, so they rely on SMS/email/tracking-by-reference only.

Tested end-to-end in `tests/Feature/InAppNotificationTest.php`.

**Scaffolded for Phase 2 (schema + models only, no dedicated UI yet):**
product reviews, wishlist, coupons, banners, product variants, and the
Marketing Admin role. Building these out is a natural next step once the
pilot validates Phase 1.

## 12. Known follow-ups

- Real product photography can replace the generated placeholder images at
  any time — just re-upload from **Products → Edit** in the admin panel.
- `AGENTS.md` / `CLAUDE.md` / `CY-Market_Terms_of_Reference 2.docx` in the
  project root are unchanged from what was already in the folder.

## Troubleshooting

**`composer install` fails with `ext-intl` / a `filament/forms` security
advisory (`PKSA-n7tx-gkfb-14yj`)**

This means PHP's `intl` extension is disabled. Composer's resolver tried:
- `filament/filament v3.3.52` → pulled in a `filament/forms` version
  Composer's audit refuses to install because of a known advisory.
- `v3.3.53`/`.54`/`.55` (the fix) → all require `ext-intl`, which isn't
  enabled on your PHP.

So there's no version of Filament ^3.3 that installs with `intl` off —
enable it (it's also required at runtime for the currency-formatted
columns in the admin panel, so this isn't optional):

1. Open `C:\xampp\php\php.ini`.
2. Find `;extension=intl` and remove the leading `;`.
3. Save, close every open terminal/Command Prompt window, open a new one.
4. Confirm: `php -m | findstr intl` should print `intl`.
5. Re-run `composer install` from the project root.

**`php artisan migrate` fails to connect to MySQL**

Confirm XAMPP's MySQL service is running (Control Panel), that the
`cymarket` database exists (Step 1), and that `DB_USERNAME`/`DB_PASSWORD`
in `.env` match your MySQL root credentials (XAMPP's default is `root`
with an empty password).
