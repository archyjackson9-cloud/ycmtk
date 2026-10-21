# CY-Market Security Review

Reviewer: security-reviewer (read-only on application code). Date: 2026-10-16.
Scope: auth, spatie roles, OrderPolicy, Filament authorization, IDOR, mass assignment, MoMo webhook, manual payment approval, PII/secrets, uploads, CSRF/XSS/injection, config/Docker, dependencies.
Method: traced routes -> middleware -> controller/resource -> service -> model; every finding below was confirmed by reading the code (not executed against a running instance). Secret values are never reproduced.

## Ranked summary

| # | Sev | Finding | Location |
|---|-----|---------|----------|
| 1 | Critical | Unauthenticated payment confirmation when MOMO_MODE is not live (webhook and sandbox-confirm); also hits manual-transfer payments; simulate is the shipped default | routes/web.php:42,98; MtnMomoPaymentService.php:101-117; CheckoutController.php:140-154 |
| 2 | Critical | Filament privilege escalation: any staff role can create/edit users (incl. Super Admin) and reset customer passwords | UserResource.php:33; CustomerResource.php:32 |
| 3 | High | Same gap on Category / DeliveryZone / Banner: create/edit/delete reachable by other staff roles | CategoryResource.php:28; DeliveryZoneResource.php:25; BannerResource.php:25 |
| 4 | High | MTN subscription key in plaintext in untracked, non-ignored `api.php`; not excluded from Docker build context | api.php:14; .dockerignore |
| 5 | High | Seeded super-admin with known password; deployment docs invite `db:seed --force` in production | UserSeeder.php:24,28; DEPLOYMENT.md:116 |
| 6 | Medium | Manual payment approval does not enforce amount; can also confirm cancelled orders | ManualPaymentService.php:53-83; PaymentResource.php:275-283; OrderService.php:63 |
| 7 | Medium | Live-mode webhook not provider-scoped, no amount/currency check, full payload logged | MtnMomoPaymentService.php:101-118,161; MtnMomoWebhookController.php:23 |
| 8 | Medium | No rate limiting on storefront login, forgot-password, track-order, registration | routes/web.php:46-86 |
| 9 | Medium | Payment proofs on public disk; unauthenticated upload endpoint keyed by reference | CheckoutController.php:163-198 |
| 10 | Medium | Guest order tracking = order number + phone, unthrottled | OrderController.php:102-123 |
| 11 | Medium | Emailed signed tracking link never expires, exposes full order PII | OrderEventMail.php:40; OrderController.php:130 |
| 12 | Medium | composer audit: league/commonmark (1 high DoS, 1 medium HTML filter bypass) | composer.lock |
| 13 | Low | Coupon `used_count` never incremented (usage_limit unenforced) | Coupon.php:45; CheckoutService.php:127 |
| 14 | Low | Enumeration, stale sessions of deactivated users, no security headers, trustProxies('*'), weak compose defaults | various |

---

## Findings

### 1. CRITICAL - Anyone can mark any payment as paid when MOMO_MODE is not `live` (default)
- Files: `routes/web.php:42` (POST `/checkout/sandbox-pay/{reference}`), `routes/web.php:98` (`/webhooks/mtn-momo`, CSRF-exempt at `bootstrap/app.php:28`), `app/Services/Payments/MtnMomoPaymentService.php:101-117,193-196`, `app/Http/Controllers/Storefront/CheckoutController.php:131-154`, `app/Providers/AppServiceProvider.php:24`.
- What is wrong: when `momo.mode != 'live'` (default is `simulate`; `docker-compose.yml:71` defaults `MOMO_MODE=simulate`, `.env.docker.example` ships `MOMO_MODE=simulate`, DEPLOYMENT.md:71 tells operators to leave it), `handleCallback()` skips the MTN re-query and calls `applyStatus($payment, $payload)` with the attacker's body. Payment lookup is `where('reference', $payload['externalId'])` with no provider filter, so it also matches `provider='manual'` payments, which are the only ones the storefront currently creates. `sandboxConfirm` has the same effect with no auth and no ownership check.
- Exploit: a guest checks out and is redirected to `/checkout/manual-pay/PAY-...` (the reference is in the URL). Then `curl -X POST https://shop/webhooks/mtn-momo -d '{"externalId":"PAY-...","status":"SUCCESSFUL"}'` (or POST the sandbox-pay form). `OrderService::markPaid` runs: stock consumed, order moves to Paid, staff are told to dispatch. Free goods, no money moved.
- Fix:
  1. Hard-fail outside local/testing: register the sandbox routes only `if (app()->environment('local','testing'))`, and in the controller `abort_unless(app()->environment('local','testing') && config('momo.mode') !== 'live', 404)`.
  2. In `handleCallback`, never apply an unverified payload in production: when `isSimulated()` and not local/testing, `abort(403)`.
  3. Scope the lookup: `Payment::where('provider','mtn_momo')->where('reference', ...)`.
  4. Make the compose/env-example default `MOMO_MODE=live` (or refuse to boot in production when simulate) and document it.
  5. Add a secret path token or IP allowlist on the callback URL as defense in depth, since MTN callbacks are unsigned.

### 2. CRITICAL - Privilege escalation through Filament user/customer resources
- Files: `app/Filament/Resources/UserResource.php:33` (only `canViewAny` defined), `app/Filament/Resources/CustomerResource.php:32`, `UserResource/Pages/{CreateUser,EditUser}.php`, `CustomerResource/Pages/EditCustomer.php`. No `UserPolicy` exists (`app/Policies` holds only `OrderPolicy`).
- What is wrong: Filament's `Resource::canCreate/canEdit/canDelete` fall back to `authorize()`, which returns allowed when no policy class exists (`vendor/filament/filament/src/helpers.php:24-28`). `canViewAny` only hides the index and navigation; the `create` and `{record}/edit` routes authorize via `canCreate()` / `canEdit()` (`CreateRecord::authorizeAccess`) and are open to every user that passes `canAccessPanel` (inventory_officer, marketing_admin, support_agent).
- Exploit: a Support Agent opens `/admin/users/create` (or `/admin/users/{superadmin-id}/edit`), sets a known password and the `super_admin` role, and logs in as Super Admin: full control of payments, settings (including the payment account number shown to customers), users, reports. Or opens `/admin/customers/{id}/edit` and sets any customer's password (account takeover).
- Fix: add to both resources
  ```php
  public static function canCreate(): bool { return auth()->user()?->isSuperAdmin() ?? false; }
  public static function canEdit($record): bool { return auth()->user()?->isSuperAdmin() ?? false; }
  public static function canDelete($record): bool { return auth()->user()?->isSuperAdmin() ?? false; }
  public static function canDeleteAny(): bool { return auth()->user()?->isSuperAdmin() ?? false; }
  ```
  and add a `UserPolicy` (super-admin only; forbid deleting/deactivating the last super admin and self-demotion). Add tests that every role other than super_admin gets 403 on `/admin/users/create`, `/admin/users/{id}/edit`, `/admin/customers/{id}/edit`.

### 3. HIGH - Same missing create/edit/delete gating on catalogue/config resources
- Files: `CategoryResource.php:28`, `DeliveryZoneResource.php:25`, `BannerResource.php:25` (only `canViewAny`).
- Exploit: an inventory officer or support agent opens `/admin/delivery-zones/{id}/edit` and sets the delivery fee to 0 (applied at `CheckoutService.php:52-53`), or defaces banners (banners accept video upload) and categories.
- Fix: add explicit `canCreate/canEdit/canDelete/canDeleteAny` mirroring `canViewAny` roles, as `ProductResource.php:39-51` already does.

### 4. HIGH - MTN subscription key in `api.php`
- File: `api.php:14` (value present; not printed here). `git status` shows it untracked; `.gitignore` does not list it and `.dockerignore` does not exclude it, so `Dockerfile:11` (`COPY . .`) bakes it into the build and the runtime image, which CI pushes to GHCR (`.github/workflows/docker-publish.yml`). The file itself warns "Do NOT commit". `reports-update.zip` is also tracked in git at the repo root (contains old copies of app code).
- Exploit: one `git add .` or a local `docker build` followed by a push publishes the key; anyone with the image or repo can use the MTN subscription.
- Fix: delete the file or move values to `.env`; add `api.php` to `.gitignore` and `.dockerignore`; `git rm` `reports-update.zip`; rotate the subscription key on the MTN portal since it has been in plaintext on disk.

### 5. HIGH - Default credentials on privileged accounts
- Files: `database/seeders/UserSeeder.php:24,28-38` (`admin@cymarket.test` Super Admin plus 4 more staff accounts, password `password`), `DatabaseSeeder.php` has no environment guard, `DEPLOYMENT.md:116` suggests `php artisan db:seed --force` on the server, SETUP.md:104 documents the password.
- Exploit: anyone who tries the documented credentials at `/admin/login` on a seeded production database gets Super Admin.
- Fix: skip `UserSeeder` when `app()->isProduction()` (or generate a random password and print it once); take the first admin from `ADMIN_EMAIL/ADMIN_PASSWORD` env; force a password change on first login.

### 6. MEDIUM - Manual payment approval: amount not enforced, order state not checked
- `ManualPaymentService.php:53-83`, `PaymentResource.php:275-283`: the modal says the amount is "checked against the order total", but `amount_received` is only stored in `raw_response` (line 69) and never compared to `payments.amount`. A staff member (or a typo) can confirm GHS 1 for a GHS 500 order; the order goes to Paid and stock is consumed. No second-approver control. The action closure does not re-check the role (it relies on `canViewAny` and `visible()`).
- `OrderService.php:63`: the idempotency guard lists only post-payment states. A payment confirmed for an order in `Cancelled` (customer cancelled, reservation expired) is not rejected: `markPaid` consumes stock and calls `transition(...Paid)` bypassing `canTransitionTo`, resurrecting a cancelled order (also reachable unauthenticated via finding 1).
- Fix: in `confirm()` reject when `abs($amountReceived - $payment->amount) > 0.009` unless an explicit variance reason is supplied (store variance and approver); in `markPaid` proceed only when the order is `PendingPayment` or `OnHold`; add `abort_unless(auth()->user()->isSuperAdmin(), 403)` inside the actions; consider two-person approval above a threshold. Audit trail is partial: `confirmed_by` and `OrderStatusHistory` are written (good), amount variance is not.

### 7. MEDIUM - Live-mode webhook hardening gaps
- `MtnMomoPaymentService.php:101-118`: in live mode the callback is correctly re-verified with MTN (robust against forgery), but (a) the lookup is not provider-scoped, so a callback naming a manual payment calls MTN with a null `provider_reference` (wasted API calls, error noise, amplifiable unauthenticated); (b) `applyStatus` (line 161) trusts MTN's status without comparing the returned `amount`/`currency` to `payments.amount` and `config('momo.currency')`; (c) no throttle on the unauthenticated endpoint.
- `MtnMomoWebhookController.php:23,30`: the full request body (payer MSISDN, names) is written to `storage/logs/momo.log` at info level.
- Idempotency is sound: `markPaid` locks the order row and returns early for already-paid orders, so replays do not double-fulfil.
- Fix: scope by provider, compare amount and currency before marking paid, add `throttle:60,1` to the route, log only reference and status.

### 8. MEDIUM - No brute-force protection on storefront auth and tracking
- `routes/web.php:46-47,74-86`; `AuthenticatedSessionController.php:28` uses `Auth::attempt` without a `RateLimiter`. Filament's own `/admin/login` is rate-limited by Filament, but the storefront login also authenticates staff accounts. Password-reset requests can be spammed to flood a victim's mailbox.
- Fix: `->middleware('throttle:5,1')` on POST login/forgot-password/reset/register/track-order, or a `RateLimiter` keyed by email+IP.

### 9. MEDIUM - Payment proof handling
- `CheckoutController.php:163-198`, routes `routes/web.php:43-44`: the page and upload endpoint are unauthenticated and keyed only by `Payment.reference` (`PAY-YmdHis-` + 6 random chars; low entropy once the creation second is known, and it leaks via browser history, referrer, or shared screenshots). Anyone with it can replace `proof_path` while Pending. Files go to the `public` disk (`store('payment-proofs','public')`, line 194) and are served at `/storage/payment-proofs/<random>` without auth: receipts (name, phone, balance) are world-readable to anyone who obtains the URL, and `public/sw.js` caches `/storage/*` on devices. Validation (`mimes:jpg,jpeg,png,pdf`, 5 MB) is sound; SVG/HTML are not allowed, so no stored XSS.
- Fix: store on the private `local` disk and serve through an authorized admin route or temporary signed URL; require ownership (session `guest_order_ids` or OrderPolicy) on `manualPay`/`manualProofUpload`; use a longer random reference (`Str::random(24)`).

### 10. MEDIUM - Guest order tracking is guessable
- `OrderController.php:102-123`: `order_number` (`CYM-Ymd-` + 5 uppercase alphanumerics) plus a phone number returns the whole order (address, name, items) and grants session access. Phone numbers are low-entropy, the order-number date is predictable, and there is no throttle (finding 8). It also matches `delivery_phone`, which the order creator controls.
- Fix: throttle (5/min/IP), generic failure message, longer random suffix, optionally an SMS OTP.

### 11. MEDIUM - Non-expiring signed tracking URL
- `OrderEventMail.php:40` uses `URL::signedRoute` (no expiry); `OrderController.php:130` lets a valid signature bypass all other checks. A forwarded or leaked email, the mail log (`MAIL_MAILER=log` writes bodies to storage/logs), or a proxy log exposes full PII permanently. Signature is path-bound, so it cannot be reused on cancel or other routes.
- Fix: `URL::temporarySignedRoute('orders.show', now()->addDays(14), ...)`; restrict the signature bypass to `orders.show`.

### 12. MEDIUM - Dependency advisories
- `composer audit`: `league/commonmark` <=2.10.1: PKSA-m4t9-vsgq-8khn (high, quadratic-time DoS in the GFM table extension) and PKSA-m2dq-1fhr-29b1 (medium, DisallowedRawHtml bypass). Relevant wherever user-controlled markdown is rendered (Filament markdown/rich fields, markdown mail). `npm audit --omit=dev`: 0 vulnerabilities.
- Fix: `composer update league/commonmark` once a patched release exists; avoid rendering user-supplied markdown until then. Add `composer audit` and `npm audit` to the CI workflow.

### 13. LOW - Coupon limits never enforced
- `Coupon::isValid` reads `used_count` (Coupon.php:45) but nothing increments it, so `usage_limit` can be exceeded indefinitely; no per-user limit and no atomic check in `CheckoutService.php:127-134`. There is no CouponResource, so exposure depends on how coupons are created.
- Fix: increment `used_count` under `lockForUpdate()` inside the checkout transaction.

### 14. LOW - Auth and hardening items
- Enumeration: `PasswordResetLinkController.php:22-26` returns the "can't find user" status for unknown emails; registration `unique:users,email` reveals existing emails. Return a generic message.
- `AuthenticatedSessionController.php:28-42`: `is_active` is checked at login only; a deactivated customer's existing session or remember-me keeps working. Add a middleware check. Staff are covered by `canAccessPanel`.
- No email verification (documented design choice).
- `SESSION_ENCRYPT=false`; `bootstrap/app.php:19` `trustProxies(at: '*')` is acceptable only because Apache is `expose`d to Caddy and not published; restrict to the proxy network if the app port is ever published.
- No security headers (CSP, frame-ancestors/X-Frame-Options, HSTS, Referrer-Policy, X-Content-Type-Options) on storefront or admin. Add via Caddy `header` directives or a middleware.
- Local `.env` has `APP_DEBUG=true`, `APP_ENV=local` (fine for XAMPP; gitignored and never committed: `git log --all -- .env` is empty). Production compose defaults are `APP_DEBUG=false`, `LOG_LEVEL=error` (good). Compose falls back to weak DB passwords (`change-me`) if the env file is missing; make them required (`${DB_PASSWORD:?set it}`).
- `OrderPolicy::cancel` (OrderPolicy.php:35) delegates to `view`, and `OrderController` calls `authorizeOrderAccess` (view) rather than `cancel`; support agents can therefore cancel or resolve holds through the storefront routes. Not exploitable by customers; align with TOR.

---

## Checked and found clean
- Mass assignment: `User` fillable is `name,email,phone,password` (no `is_active` or role); `Order`/`Payment`/`Address` have broad `$fillable`, but every controller passes validated arrays or server-built data (`CheckoutService`, `AddressController` creates via `$request->user()->addresses()`), so no client-controlled `user_id`/`status`/`total`. Registration always assigns the `customer` role server-side.
- IDOR: `AddressController::update/destroy` check `user_id`; `CartController::authorizeItem` binds items to the caller's cart; `NotificationController` queries `$user->notifications()`; `OrderController` uses OrderPolicy, session `guest_order_ids`, or a signature; `/my-orders` is scoped to the caller. Prices, totals and delivery fee are computed server-side; no client amount tampering.
- Admin gate: `User::canAccessPanel` requires `is_active` and a staff role; Filament panel uses `AuthenticateSession` and `VerifyCsrfToken`. Settings page, Reports pages, widgets, `PaymentResource`, `OrderResource` actions and `ProductResource` writes are role-gated.
- CSRF: only `webhooks/mtn-momo` is exempt (appropriate for a server callback; see findings 1 and 7).
- XSS: no `{!! !!}` in views; Blade escaping throughout; `@json` used for the poll URL; no `innerHTML`/`x-html` in app JS.
- SQL injection: all `selectRaw/whereRaw/orderByRaw` use static SQL or bound parameters (ReportingService.php, ProductResource.php:153).
- CSV export (`ReportExportController.php:253-262`) neutralizes formula injection and enforces roles.
- Redirects: `redirect()->away($result['redirect_url'])` is built server-side from `URL::route`; `NotificationController::open` redirects to server-generated `data['url']`.
- Passwords: hashed cast, bcrypt 12 rounds, reset flow uses the Laravel broker with `Rules\Password::defaults()`.
- Secrets: `.env` is gitignored and excluded from the Docker context, never in git history; `.env.docker.example` holds placeholders; MoMo/SMS keys read from env. (Exception: `api.php`, finding 4.)
- Concurrency: `markPaid`/`cancel`/`updateStatus` take `lockForUpdate` on the order row; webhook replays are idempotent for already-paid orders.
- npm audit (prod deps): no vulnerabilities.

## Suggested hardening
1. Add feature tests: every non-super-admin role gets 403 on all Filament create/edit/delete routes; `/webhooks/mtn-momo` and sandbox routes cannot confirm payments in production env or for `provider=manual`.
2. Introduce policies for User, Payment, Product, Category, DeliveryZone, Banner plus a `Gate::before` for super admin, instead of per-resource static checks.
3. Activity log (who/when/old/new) for payment confirmation/rejection, settings changes (especially the Pay-by-Transfer account number, which controls where customers send money) and user/role changes; require password re-confirmation to change the account number.
4. Run containers as non-root, pin image digests, scan the image (Trivy) in CI before pushing to GHCR.
5. Rotate the MTN subscription key (finding 4).

Fixes can be handed to backend-dev (findings 1-3, 5-8, 13) and tester (regression tests above). No application code was modified.
