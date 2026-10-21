# CY-Market Architecture Audit

Role: Architect (read-only). Scope: migrations, models, services, Filament resources, storefront controllers, routes, config, Docker/CI, README/SETUP/DEPLOYMENT, and the TOR v2.1.
Method: static review only. No application code was modified and no tests were run. Line numbers are against the working tree as of this audit.

Severity scale: **Critical** = money/stock integrity or remote abuse today; **High** = will bite in the pilot; **Medium** = scale/maintainability; **Low** = hygiene.

---

## 0. System picture (what exists)

- Modular monolith: Laravel 13, Filament 3 admin, Blade storefront. Services hold the logic (`CartService`, `CheckoutService`, `OrderService`, `StockService`, `Payments/*`, `Notifications/*`, `ReportingService`, `SettingsService`).
- Order flow: Cart -> `CheckoutService::checkout` (one DB transaction: order + items + stock reservation + payment row + gateway call) -> `PendingPayment` -> gateway confirms (manual by staff today, MoMo dormant) -> `OrderService::markPaid` -> `StockService::consumeForOrder` -> `Paid` -> staff move it to Processing, Dispatched, Delivered, Completed.
- Stock model: `products.available_quantity` (on hand), `products.reserved_quantity` (denormalised sum of active `stock_reservations`), `stock_movements` (audit). Sellable = available - reserved.
- Live payment method: manual "pay by transfer". MoMo is complete but disabled in the UI.
- Runtime: single Apache container + scheduler loop + idle queue worker + MySQL + Caddy. Sessions, cache and queue all use the `database` driver. Uploads are on a local Docker volume.
- Everything customer-facing (SMS, email, in-app) is synchronous. `ShouldQueue` is not used anywhere.

What is good and should be kept: row-locked order transitions, `order_status_histories`, `stock_movements` audit, gateway interface + resolver, snapshot columns on `order_items`/`orders`, signed email tracking links, CSV-injection guard in the export, scheduled sweepers, a strict `OrderStatus` state machine.

---

## 1. Ranked findings

### CRITICAL

#### C-1. Anyone can mark any payment as paid (simulate mode is the default and is not provider-scoped)
- `app/Http/Controllers/Storefront/CheckoutController.php:140-154` (`sandboxConfirm`), `routes/web.php:41-42` and `:98` (webhook), `app/Services/Payments/MtnMomoPaymentService.php:101-117` and `:193-196`, `app/Providers/AppServiceProvider.php:24`.
- Why it hurts:
  - `MOMO_MODE` defaults to `simulate` in `.env.example`, `docker-compose.yml` and `config/momo.php`, and DEPLOYMENT.md step 3 tells operators to leave it that way. The MoMo UI is off, but the routes are not.
  - `sandboxConfirm` only checks "mode is not live". It calls the MoMo service's `handleCallback`, which looks the `Payment` up by `reference` with no `provider` filter (`:103`) and then, in simulate mode, applies the supplied status as truth (`:112-118`).
  - A customer sees their own manual-payment reference in the URL `/checkout/manual-pay/{reference}`. `POST /checkout/sandbox-pay/{reference}` with `approve=1` (no auth, CSRF token available from the same session) flips their manual payment to `Successful`. `markPaid` then deducts stock and the order enters fulfilment unpaid.
  - `POST /webhooks/mtn-momo` is CSRF-exempt and does the same with a JSON body `{externalId, status: SUCCESSFUL}`.
- Severity: Critical (free goods).
- Solution:
  1. Fail closed. Default `momo.mode` to `disabled`. Register the `checkout.sandbox-*` routes only when `app()->environment(['local','testing'])` and mode is `simulate`. Add a boot-time assertion that refuses `simulate` when `APP_ENV=production`.
  2. In `MtnMomoPaymentService::handleCallback` (and every lookup by reference) constrain `->where('provider','mtn_momo')`. Sandbox confirm must reject non-MoMo payments.
  3. While MoMo is dormant, return 404 from the webhook route unless mode is `live`.
- Handoff: **backend-dev** (1-3), **security-reviewer** (verify, pen-test the three routes), **tester** (regression: manual payment cannot be confirmed through sandbox/webhook), **doc-writer** (DEPLOYMENT.md must stop saying "leave simulate").

#### C-2. A late or staff-confirmed payment can resurrect a cancelled order
- `app/Services/OrderService.php:56-90` (`markPaid`), `:219-231` (`transition` does no validation), `app/Services/StockService.php:100-146` and `:202-229`, `app/Services/Payments/ManualPaymentService.php:53-81`, `app/Filament/Resources/PaymentResource.php:91-117`.
- Why it hurts:
  - The reservation sweeper cancels `PendingPayment` orders after the hold (24h for manual) but leaves their `Payment` row `Pending`.
  - Admin > Payments still shows **Confirm Payment** for that row (it only checks `provider=manual && status=Pending`).
  - `markPaid` only short-circuits for already-paid statuses. For a `Cancelled` order it runs `consumeForOrder` (which checks only `available_quantity`, ignoring other people's reservations) and then `transition(..., Paid)`. `transition` writes whatever status it is given, so `Cancelled -> Paid` succeeds and `cancelled_at` stays set.
  - Result: stock deducted without a reservation, an order that is both cancelled and paid, wrong reports, and a possible oversell that surfaces later as OnHold.
- Severity: Critical (money/stock integrity). Realistic for manual transfers, where customers pay hours late.
- Solution:
  1. `markPaid`: branch on status. If `Cancelled`, record the payment as `Successful` but flag it **late/orphan** (see C-5 refunds design) and do not touch stock or status. Alert staff: refund or reinstate.
  2. `OrderService::cancel` and the sweeper must set the order's pending payments to `Cancelled`.
  3. `PaymentResource` Confirm/Reject visible only when `order.status = PendingPayment`.
  4. Make `transition()` enforce `canTransitionTo`, with an explicit `force: true` for the two sanctioned system jumps (PendingPayment -> OnHold). Today the state machine is bypassed by the one caller that needs it (`PendingPayment -> OnHold` at `:70` is not in `allowedNextStatuses`).
- Handoff: **backend-dev**, **tester** (order lifecycle: pay-after-cancel, double-confirm, reject-then-confirm), **security-reviewer** (audit trail of forced transitions).

#### C-3. Reservation does not check availability: overselling is built in
- `app/Services/StockService.php:36-49` (`reserveForOrder`), `:112` (`consumeForOrder`), `app/Services/CartService.php:54` and `:141`.
- Why it hurts:
  - The only sellable check is in the cart, done without a lock and long before checkout. `reserveForOrder` takes the row lock but never verifies `available - reserved >= qty`; it just increments `reserved_quantity`. Two concurrent buyers of the last 5 kg both get an order and a reservation (`reserved = 10`, `available = 5`).
  - The loser is discovered only at payment time (`consumeForOrder`) and ends up On Hold with money received, which is the exact failure TOR section 11 wants to be the exception.
  - Locks are taken in cart-item order, not by product id, so two checkouts with the same two products in opposite order can deadlock on MySQL.
- Severity: Critical for popular items (the TOR calls this out explicitly).
- Solution: inside `reserveForOrder`, lock products ordered by id (`whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get()`), assert `available_quantity - reserved_quantity >= qty` for every line, and throw a `CheckoutException` naming the short item. The exception rolls back the whole checkout transaction, so cart and stock stay clean. Keep `consumeForOrder` as the second line of defence. Add a retry on deadlock (`DB::transaction($fn, 3)`).
- Handoff: **backend-dev**, **tester** (needs a MySQL-backed concurrency test; SQLite ignores `lockForUpdate`, see H-8).

---

### HIGH

#### H-1. Cancelling an On-Hold order adds stock that was never deducted; credit path ships ghost stock
- `app/Services/OrderService.php:171-176` (`wasPaid` includes `OnHold`), `:206-214` (`resolveOnHold`), `app/Services/StockService.php:100-179`.
- On Hold is entered precisely because `consumeForOrder` returned false, so no stock was consumed. Cancelling it (customer picks refund) sets `wasPaid=true` and runs `restockForOrder`, which increments `available_quantity` again: inventory is inflated by the order quantity. The "credit" branch moves the order straight to Processing without ever consuming stock (ghost stock the other way). `sold_count` is incremented on consume (`:130`) but never decremented on restock, so best-seller ranking drifts.
- Solution: derive "net consumed per product for this order" from `stock_movements` (`Out` minus `In` rows with `reference_type=order`, `reference_id=order.id`) and restock only that. For the credit path, call `consumeForOrder` again on resume, and keep On Hold if still short. Decrement `sold_count` on restock (or compute it from movements). Add `cymarket:audit-stock` to reconcile `reserved_quantity` against active reservations and `available_quantity` against the movement ledger.
- Handoff: **backend-dev**, **tester**.

#### H-2. Refunds and credits are logged, not implemented
- `app/Services/OrderService.php:184-190` writes a log line; `PaymentStatus::Refunded` exists but nothing sets it; `RefundPreference::Credit` has no balance anywhere; `ReportingService.php:67-71` infers "refunded" from `Cancelled` + a `Successful` payment, so a refund owed and a refund paid are indistinguishable.
- This breaks acceptance criterion 15.1 ("refund/credit preference is honoured") and the audit trail NFR.
- Design (migration-ready):
  - `refunds`: id, order_id FK, payment_id FK, amount decimal(10,2), method enum(`credit`,`momo`,`manual`), status enum(`requested`,`approved`,`paid`,`failed`,`cancelled`), reason, requested_by, processed_by FK users nullable, provider_reference nullable, processed_at, timestamps. Index (status, created_at), unique (payment_id, status) where status in open states.
  - `customer_credits`: id, user_id FK nullable, phone nullable (guests), order_id FK, amount, remaining, expires_at nullable, timestamps; apply at checkout as a discount line (`orders.credit_applied`).
  - Add `refund(Payment, amount)` to `PaymentGatewayInterface` (manual = creates a task for staff, MoMo = disbursement later).
  - Admin: `RefundResource` (Super Admin) with Approve / Mark paid actions. Reports use `refunds.status = paid`.
- Handoff: **architect** (this section is the design), **backend-dev**, **frontend-dev** (customer credit display at checkout), **tester**, **doc-writer**.

#### H-3. Manual confirmation never verifies the amount
- `app/Filament/Resources/PaymentResource.php:106-114`, `app/Services/Payments/ManualPaymentService.php:53-81` (`amount_received` is only buried in `raw_response` at `:69`).
- Staff can confirm GHS 10 against a GHS 400 order and the order goes to Paid. The helper text claims it is "checked against the order total"; it is not.
- Solution: add `payments.amount_received decimal(10,2) null`. In `handleCallback`, if `amount_received < amount` leave the payment `Pending` with an `underpaid` note (or add `PaymentStatus::Partial`) and require an explicit "accept shortfall" override with a reason; if greater, record the overpayment for a refund (H-2). Validate in the Filament form (`minValue`, a live warning).
- Handoff: **backend-dev**, **tester**, **security-reviewer** (separation of duties: consider requiring a second admin for confirmations above a threshold).

#### H-4. Unauthenticated stock-holding and brute-force surface; zero rate limiting
- `CheckoutController.php:58-105`, `CheckoutService.php:100`, `SettingsService::manualPaymentReservationHours` (24 h default), `OrderController.php:102-122` (track), `AuthenticatedSessionController.php:28`, `RegisteredUserController.php:22`, `PasswordResetLinkController`, `CheckoutController.php:182-198` (proof upload). `grep -r "throttle|RateLimiter"` over `app/`, `routes/`, `bootstrap/` returns nothing.
- Impact:
  - A bot places guest orders (no verification, no cap) for the full stock of every product and holds them for 24 h, then repeats: a cheap denial of sale.
  - Login, register, forgot-password and track-order (order number + phone) are brute-forceable. Track-order discloses name/address/phone.
  - The proof upload endpoint accepts 5 MB files per request, unauthenticated, unthrottled.
- Solution:
  1. Named limiters in `AppServiceProvider::boot`: `login` (5/min per email+IP), `register` (10/hour/IP), `track` (10/min/IP), `checkout` (5/hour per IP and per phone), `upload` (10/hour).
  2. Business cap: at most 2 open `PendingPayment` orders per phone and per session; reject with a clear message.
  3. Shorter initial hold for manual payment (for example 2 h) that extends to 24 h only after a proof is uploaded or staff acknowledge. This keeps the "human confirms later" model without letting anonymous users lock inventory for a day.
  4. Optional: phone OTP or captcha for guest checkout (security-reviewer to decide).
- Handoff: **backend-dev** (1-3), **security-reviewer**, **tester**.

#### H-5. Synchronous external I/O inside DB transactions that hold row locks
- `OrderService.php:56-100` (`markPaid` calls `notify` x2-3 inside `DB::transaction`), `:125-140` (`cancel`), `CheckoutService.php:50-111` (gateway HTTP call at `:108` inside the checkout transaction), `SmsNotificationService.php:53-80` (3 immediate retries, 15 s timeout each, no backoff), `EmailNotificationService.php:55` (SMTP), `OrderEventMail.php:18` (explicitly not queued).
- Impact: a slow SMS or SMTP provider holds the order and product row locks for up to ~90 s per event, blocking every other checkout touching those products and stalling Filament actions. A gateway timeout during checkout does the same with the reservation locks held. The "MoMo failure rolls back the order" comment in `CheckoutService.php:103-107` also means a charge could succeed at MTN while the local transaction later fails.
- Solution:
  1. Move all notifications to queued jobs (`SendOrderSms`, `SendOrderEmail`) dispatched `afterCommit()`. The `queue` container and `QUEUE_CONNECTION=database` already exist. SMS retry becomes `$tries=3`, `backoff=[30,120,600]` instead of a tight loop.
  2. Split checkout into two phases: (a) commit order + items + reservation + `Payment(pending)`; (b) call the gateway outside the transaction; on failure run a compensating action (`markPaymentFailed` + cancel + release). Never hold locks across HTTP.
  3. Keep `NotificationLog` as the source of truth for delivery state; update it from the job.
- Handoff: **backend-dev**, **tester**, **doc-writer** (queue worker is no longer "idle safety net"; update DEPLOYMENT.md).

#### H-6. Every anonymous page view writes to the database
- `app/Http/Middleware/ShareStorefrontData.php:22-31`, `app/Services/CartService.php:18-31` (`firstOrCreate`).
- The storefront middleware calls `currentCart()` on every request to show the badge count. For a guest that is `Cart::firstOrCreate` keyed by session id. Bots, link previewers, uptime checks and PWA prefetches each create a `carts` row (plus a `sessions` row). `MarkAbandonedCarts` only flips `status`; nothing ever deletes. The mega-menu category tree (`:22-27`) is also re-queried uncached on every request.
- Solution: make the badge read-only. Look up an existing active cart without creating one (or store the cart id in the session and count from `cart_items` with one query). Create the cart lazily in `addItem`. Cache the category tree (`Cache::remember`, bust in `Category::saved/deleted`). Add `cymarket:prune-carts` (delete abandoned carts with no items older than N days; delete abandoned carts older than 30 days). Add a unique/guard for one active cart per user (see M-3).
- Handoff: **backend-dev**, **frontend-dev** (cart badge via the new accessor), **tester**.

#### H-7. Zombie `PendingPayment` orders after a failed or rejected payment
- `OrderService.php:101-114` (`markPaymentFailed` releases the reservation but leaves the order `PendingPayment`), `StockService.php:204` (the sweeper selects only *active reservations*), no retry-payment route in `routes/web.php`.
- After a staff **Reject** or a MoMo failure, the order stays Pending Payment forever: no reservation (so the sweeper never sees it), no way for the customer to retry, still counted in "Orders Awaiting Action" (`StatsOverviewWidget.php`), still cancellable only by hand. The TOR says "cart preserved, retry option, no order created".
- Solution: choose one: (a) reject/fail cancels the order (`cancelled_reason = payment_failed`) and the customer starts a new checkout (the cart is already `converted`, so also reopen it: set cart back to `active` and recreate items from the order); or (b) add `POST /orders/{order}/retry-payment` that re-reserves under the C-3 rules and creates a new `Payment`. Recommend (a) for the pilot, (b) when MoMo returns. In both cases make the sweeper iterate orders (`status=PendingPayment AND placed_at < now - hold`) rather than reservations.
- Handoff: **backend-dev**, **frontend-dev** (retry/reorder button), **tester**.

#### H-8. CI does not run tests; the concurrency-critical code is tested only on SQLite
- `.github/workflows/docker-publish.yml` builds and pushes `:latest` on every push to `main` with no test step. `phpunit.xml` / `tests/` run on SQLite, where `lockForUpdate()` is a no-op, so none of the 1,216 lines of feature tests can detect C-2/C-3/H-1 style races. There is no test for rate limits, authorisation of `/orders/{order}`, the webhook, or the sandbox routes.
- Solution: add `ci.yml` with a MySQL 8 service, `composer install`, `php artisan test`, and a `needs:` gate on the publish job. Tag images with the SHA and deploy by SHA, not `latest`. Add a small parallel-process concurrency test (two PHP processes racing `reserveForOrder`) that runs only against MySQL.
- Handoff: **tester**, **backend-dev** (workflow), **doc-writer**.

---

### MEDIUM

#### M-1. Coupons: usage never counted, no admin, float money
- `app/Services/CheckoutService.php:121-132` (`resolveCoupon`) never increments `coupons.used_count`, so `usage_limit` is unenforceable (`grep used_count app/` finds only the model). There is no `CouponResource`, no per-customer limit, no `coupon_id` on the order (only the `coupon_code` string, `orders` migration `:44`), and `Cart.coupon_code` is unused. `Coupon::discountFor` (`Coupon.php:458-466`) returns an unrounded float; the DB truncates to 2 dp while `total` was computed from the float.
- Solution: `coupon_redemptions(id, coupon_id, order_id unique, user_id nullable, phone nullable, amount, created_at)`. Redeem inside the checkout transaction with an atomic `UPDATE coupons SET used_count=used_count+1 WHERE id=? AND (usage_limit IS NULL OR used_count<usage_limit)` and check affected rows; release on cancel before payment. Round discounts half-up to 2 dp at one place. Add `CouponResource` for the marketing-admin role.
- Handoff: **backend-dev**, **frontend-dev** (checkout coupon feedback), **tester**.

#### M-2. Money is handled as PHP floats
- `Cart::subtotal` (`Cart.php:506`), `CheckoutService.php:66` (`max(0, $subtotal + $fee - $discount)`), `CartService.php:160` (float `!==` comparison on prices), reports sum with `(float)`. Fine for the pilot at 2 dp, but rounding drift and float equality bugs will appear with percentage coupons and variants.
- Solution: introduce a tiny `Money` value (integer pesewas) or use `brick/money`/`bcmath` in the cart, checkout and coupon code paths only; keep `decimal(10,2)` columns. One rounding rule documented in one class.
- Handoff: **backend-dev**, **tester**.

#### M-3. Index and constraint gaps
| Table / column | Used by | Problem | Fix |
|---|---|---|---|
| `orders.delivery_phone`, `orders.guest_phone` | `OrderController.php:108-112` (`orWhere` on two unindexed columns) | full scan per track attempt | normalise to E.164 on write; index `(delivery_phone)` and `(guest_phone)`, or one `contact_phone` column |
| `orders (user_id, created_at)` | `/my-orders` (`user_id` index then filesort) | filesort | composite |
| `orders (paid_at)` | all revenue reports should key on payment date | missing | index; see M-6 |
| `carts (user_id, status)`, `carts (status, updated_at)` | `currentCart`, `MarkAbandonedCarts` | no composite, no uniqueness; races create duplicate active carts | composite indexes; enforce one active cart per user (generated column `active_user_id` + unique, or app lock) |
| `cart_items unique(cart_id, product_id, product_variant_id)` (`2026_01_01_000011`) | `CartService::addItem` `firstOrNew` | MySQL treats NULL as distinct, so the unique never fires when `product_variant_id` is NULL (always, today): duplicate lines under double-click | partial/generated key `COALESCE(product_variant_id,0)` or `lockForUpdate` the cart in `addItem` |
| `payments (status, provider, created_at)` | `ReconcileMomoPayments`, admin filters | none | composite index |
| `payments.order_id` + `status` | `Order::payments()->where status`, report `whereHas` | FK only | composite `(order_id, status)` |
| `notification_logs (status, created_at)` | admin undelivered view | `(channel,status)` only | composite |
| `order_status_histories (order_id, created_at)` | timeline `latest()` | FK only | composite |
| `products (is_active, sold_count)`, `(is_active, created_at)` | shop sort + home rails (`ProductController.php:385-393`, `HomeController.php`) | filesort on every list | composites (catalogue is small today; do it with the next migration) |
| `stock_reservations (order_id, status)` | `releaseForOrder`, `consumeForOrder` | FK only | composite |
- Also: `orders.coupon_code` is a free string (no FK); `stock_reservations.cart_id` and `carts.coupon_code` are dead columns; `products.reserved_quantity` is a denormalised copy of `stock_reservations` with no integrity check (see H-1 audit command).
- Handoff: **backend-dev** (one migration), **tester**.

#### M-4. Search is `LIKE '%term%'` over four columns plus a category subquery
- `app/Models/Product.php:158-172`. Leading-wildcard LIKE on `name`, `description` (TEXT), `sku` plus `orWhereHas('category')` cannot use an index. It is acceptable at tens of products, not at thousands. No typo tolerance, no faceting by rating/attribute (TOR 6.2).
- Solution: interim MySQL `FULLTEXT(name, short_description, sku)` with `MATCH ... AGAINST` behind the existing `scopeSearch`. Phase 2: Laravel Scout + Meilisearch (TOR 5.3) behind a `ProductSearch` interface so controllers do not change.
- Handoff: **backend-dev**, **frontend-dev** (filters UI), **tester**.

#### M-5. Per-view write amplification and dead denormalised counters
- `ProductController.php:73` `increment('views_count')` on every product GET: a write per bot hit and a hot-row lock on popular products. `average_rating`/`ratings_count` are never maintained (reviews have no routes), yet `sort=rating` is offered (`ProductController.php:391`). `sold_count` drifts on cancel/restock (H-1).
- Solution: buffer views in cache (`Cache::increment`) and flush every few minutes, or drop the column until analytics exist. Hide `rating` sort until reviews ship. Recompute `sold_count` from movements in the audit command.
- Handoff: **backend-dev**, **frontend-dev**.

#### M-6. Reporting design: non-sargable, wrong date basis, memory-bound, uncached
- `ReportingService.php:100` and `:123` use `date(created_at)` (defeats the `(status, created_at)` index and is DB-specific); revenue is keyed on `created_at` not `paid_at`, so an order created on the 30th and paid on the 1st lands in the wrong month; `fulfilmentPerformance` (`:404-440`) loads every order in the period into PHP to average timestamps; `customerReport` (`:293-333`) builds a subquery over all orders; paid-but-On-Hold orders are excluded from gross although cash was received; `topProducts` (`:179`) includes inactive items; the dashboard widget calls `customerReport` and `salesSummary` on every render (`StatsOverviewWidget.php:60`), and Filament 3 stats widgets poll every 5 s by default (verify; set `$pollingInterval = null` or 60 s).
- TOR section 9 lists a `ReportSnapshot` entity; it does not exist.
- Solution: `daily_sales_snapshots(date PK, orders, gross, net, refunds, delivery_fees, new_customers, ...)` plus `daily_product_snapshots`, filled by a nightly `cymarket:snapshot-reports` job (idempotent upsert, also rebuilds a given day). Dashboards read snapshots plus "today" live. Use range predicates (`>= start AND < end`), key on `paid_at`, compute averages with SQL (`TIMESTAMPDIFF`, with a driver switch for SQLite tests) or from snapshots. Cache widget numbers 60 s.
- Handoff: **backend-dev**, **tester**, **doc-writer** (definition of "revenue" in one place).

#### M-7. Filament N+1 and unbounded option lists
- `OrderResource.php:69` calls `customerName()` per row -> one `users` query per row; no `getEloquentQuery()->with('user')` override (overrides exist only in `CustomerResource`/`UserResource`). `ProductResource` image column should `with('images')`/`category`. `StockMovementResource.php:494` and `:523` load the entire `products` table into select options on every page render. `LowStockProductsWidget.php:38` runs the low-stock query, plucks ids, then queries again.
- Solution: eager-load in `getEloquentQuery`, use `->relationship('product','name')->searchable()` for the selects, and give the widget a direct query scope (`Product::lowStock()`).
- Handoff: **backend-dev**.

#### M-8. Settings cache design flaws
- `app/Services/SettingsService.php:16` caches the **fallback default** forever when a row is missing, so later `.env`/config changes are ignored until someone saves the setting; `CACHE_STORE=database` makes every `get()` a query, and checkout alone calls it 4-5 times.
- Solution: load all settings in one query per request (or a single cached array keyed `settings.all`, busted in `Setting` saved/deleted), apply the default at read time, never cache it. Move to Redis (see D-1).
- Handoff: **backend-dev**.

#### M-9. Payment concurrency and idempotency gaps
- Webhook, order-page poll (`OrderController.php:47-62`) and the reconcile job can all run `applyStatus` at once: the `isTerminal` check (`MtnMomoPaymentService.php:107-110`, `ManualPaymentService.php:57`) reads without a lock. `markPaid` is safe (order lock), but `markPaymentFailed` has no idempotency guard (`OrderService.php:101-114` notifies every call), so a duplicate callback sends a second "payment failed" SMS. The poll endpoint triggers an outbound MTN request per poll per viewer, unthrottled.
- Solution: wrap callback handling in a transaction that does `Payment::whereKey()->lockForUpdate()` and re-checks status; make `markPaymentFailed` a no-op when the payment already had a failure processed; throttle the poll (2/10 s) and cache the last gateway check for 5 s.
- Handoff: **backend-dev**, **tester**.

#### M-10. Delivery zone is optional and unvalidated; TOR 11 rule not implemented
- `CheckoutController.php:69` `nullable|exists:delivery_zones,id` (inactive zones accepted); `CheckoutService.php:52-53` silently falls back to the global fee when no zone is chosen. TOR section 11: "Delivery address outside pilot zone: system prevents checkout".
- Solution: require the zone, restrict to `is_active`, and show the fee per zone. Keep the lat/lng for logistics but do not trust it for zoning.
- Handoff: **backend-dev**, **frontend-dev**.

#### M-11. Payment proofs are public files
- `CheckoutController.php:194` stores proofs on the `public` disk; `PaymentResource.php:76` links via `Storage::disk('public')->url`. Receipts contain names, phone numbers and balances; anyone holding the URL can read them, and `/storage` is served directly by Apache. Replacing a proof orphans the old file. The endpoint is reference-authorised only and unthrottled.
- Solution: store on the private `local` disk, stream through an authorised admin route (Super Admin) or a short-lived signed URL, delete the previous file on replace, mime-sniff and re-encode images.
- Handoff: **backend-dev**, **security-reviewer**.

#### M-12. Staff alerts and escalation are log lines
- `EscalateStaleOrders.php:32` only `Log::warning`s, and re-warns hourly for the same orders with no state; TOR 11 requires an alert to Super Admin. `notifyStaffOfNewOrder` (`OrderService.php:433`) creates an in-app bell only; TOR 6.6 asks for an internal SMS on new order. `notifyInventoryOfficers` sends to a single `internal_alert_phone` setting.
- Missing from TOR 11 / 5.4: daily payment-reconciliation report, "manual override on stuck orders" (the state machine has no sanctioned override), auto-close Delivered -> Completed after the dispute window (nothing moves orders to Completed).
- Solution: `orders.escalated_at`, set once by the command, which dispatches a notification (bell + email + SMS) to Super Admins; add `cymarket:complete-delivered-orders` (configurable days); a `ReconciliationReport` page/CSV; a Super-Admin-only `forceStatus` action that writes a history row with a mandatory reason.
- Handoff: **backend-dev**, **frontend-dev** (admin pages), **tester**, **doc-writer**.

#### M-13. Auth and session hardening
- Login (`AuthenticatedSessionController.php:28`) authenticates before checking `is_active`; an already-logged-in user who is later deactivated keeps access to storefront routes (the check lives only in the login action and `canAccessPanel`). No email verification, no throttling (H-4), no 2FA for admin accounts (Filament supports it via plugins). Registration assigns the `customer` role in a second statement outside a transaction.
- Solution: an `EnsureUserIsActive` middleware on the storefront and admin groups; `Registered` event listener creates the role atomically; evaluate Filament MFA for Super Admin; add password-reset throttling.
- Handoff: **security-reviewer**, **backend-dev**.

#### M-14. Variants are a dead schema that will force a breaking change
- `product_variants`, `cart_items.product_variant_id`, `order_items.product_variant_id` exist, but `CartService.php:48` forces `null`, stock lives only on `products` (`available_quantity`, `reserved_quantity`), `StockService` and `stock_reservations`/`stock_movements` have no variant key. Adding variants later (TOR 6.1/Phase 2 acceptance) means moving stock to the variant and touching every stock path.
- Solution (do it while tables are small): every product gets one auto-created default variant; move `available_quantity`, `reserved_quantity`, SKU and optional price override to `product_variants`; add `product_variant_id` (not null) to `stock_reservations` and `stock_movements`; `products.available_quantity` becomes a computed/cached sum. Cart and order lines always carry a variant id. This makes the Phase 2 UI additive instead of a re-architecture.
- Handoff: **architect** (detailed design before coding), **backend-dev**, **frontend-dev**, **tester**. Schedule before go-live data exists.

#### M-15. Payment abstraction is MoMo-shaped; TOR says Hubtel
- `AppServiceProvider.php:24` binds `PaymentGatewayInterface` to the MoMo class; webhook and sandbox depend on that binding; `PaymentGatewayResolver::forProvider` defaults every unknown provider to MoMo (`:44`); `isTerminal`/status application is duplicated in both gateways; gateways call `OrderService` directly. The interface has no `refund()`/`verify()`. Documentation (TOR, SETUP, DB comments on `payments`, test names) still says Hubtel in places while the code is MTN.
- Solution: introduce `PaymentService` as the only caller of `OrderService::markPaid/markPaymentFailed`; gateways return a normalised `PaymentResult(status, providerRef, rawPayload)`; one shared state-application routine with the payment-row lock (M-9); resolver throws on unknown provider; update docs to say "MTN MoMo (Hubtel replaced)".
- Handoff: **backend-dev**, **doc-writer**.

---

### DEPLOYMENT AND SCALABILITY

#### D-1. Everything stateful sits in one MySQL on one VPS
- `docker-compose.yml`: `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` all `database`; uploads on a local named volume; one `app` container; no Redis, no CDN/object store, no staging, no backups. TOR 5.3/5.4 require Redis, S3-compatible storage + CDN, three environments, daily backups with restore drills, blue/green or rolling deploys.
- Consequences: sessions + cache writes compete with order transactions for the same InnoDB buffer and locks (and H-6 multiplies them); the cache table and `sessions` table grow; losing the VPS volume loses both DB and every product image.
- Solution (in order): (1) add a `redis:7` service and set session/cache/queue to Redis (config only); (2) add `mysqldump`/`mariadb-backup` cron container writing to off-box storage plus a `storage/app/public` rsync, with a documented restore drill; (3) S3-compatible disk for uploads (`FILESYSTEM_DISK=s3`) behind a CDN; (4) a staging compose file; (5) `docker compose up --no-deps -d app` rolling restart with a health gate.
- Handoff: **backend-dev** (config), **doc-writer** (runbook), **security-reviewer** (backup encryption, secrets).

#### D-2. Entrypoint runs in all containers; concurrent migrations and chown
- `Dockerfile` sets `ENTRYPOINT entrypoint.sh` for the image and `docker-compose.yml` reuses the same image for `app`, `scheduler`, `queue` with only `command:` overridden. With `RUN_MIGRATIONS=true` (offered in DEPLOYMENT.md) all three containers run `php artisan migrate --force` simultaneously, and each runs `chown -R` on the shared volume and rebuilds caches.
- Solution: run migrations in a one-shot `migrate` service (`restart: "no"`, `depends_on` db healthy) and make `app` depend on it; keep entrypoint idempotent and only cache config in `app`. Replace the `while true; sleep 60` scheduler script (drifts and can skip a minute) with `php artisan schedule:work`.
- Handoff: **backend-dev**, **doc-writer**.

#### D-3. Unsafe fallbacks and ops gaps
- `docker-compose.yml` falls back to `DB_PASSWORD=change-me` and `DB_ROOT_PASSWORD=change-me-too` silently, and `APP_KEY` empty. `.env.example` has `APP_DEBUG=true`, `DB_CONNECTION=sqlite`, and `MOMO_CURRENCY=EUR` while compose defaults GHS; mixing these is how a production box ends up in debug/simulate. `LOG_STACK=single` produces an unbounded `laravel.log` on the volume (use `daily` with retention, and ship errors to Sentry/Flare). Uptime: `routes/api.php` serves `/api/health` and the framework exposes `/up`, but nothing monitors either. `trustProxies(at: '*')` (`bootstrap/app.php:19`) is acceptable only because `app` is `expose`d, not published; keep it that way and pin to the Caddy network.
- Solution: use `${VAR:?required}` for DB passwords and APP_KEY in compose so a missing value fails the deploy; add a production `.env.docker.example` check in CI; set `LOG_STACK=daily`; add an external uptime check on `/up`.
- Handoff: **backend-dev**, **security-reviewer**, **doc-writer**.

#### D-4. Queue worker is configured as an afterthought
- `queue` service runs `queue:work --tries=3 --backoff=10` with no `--max-time/--max-jobs` (memory growth), no failed-job alerting, one worker, one queue. Once H-5 lands this becomes load-bearing.
- Solution: `--max-time=3600 --memory=256`, separate `notifications` and `default` queues, `queue:failed` surfaced in Filament (a `FailedJobsResource`), `queue:monitor` in the scheduler.
- Handoff: **backend-dev**, **frontend-dev** (admin page).

---

### LOW / HYGIENE

- **L-1.** `README.md` is the unmodified Laravel boilerplate; there is no project README (architecture, flows, run, test, deploy). SETUP.md and DEPLOYMENT.md are good but contradict the code in places (C-1, Hubtel naming). **doc-writer**.
- **L-2.** Untracked `api.php` in the repo root contains a MoMo sandbox setup script that its own header says holds a subscription key. Move it outside the repo or into `.gitignore` before anyone runs `git add .`. `reports-update.zip` is tracked in git (binary artefact; remove from history if it contains anything non-public). **backend-dev**, **security-reviewer**.
- **L-3.** `Order::generateOrderNumber` and `Payment::generateReference` use exists-then-insert loops (race is tiny; the unique index backs it, but a collision surfaces as a 500). Retry on `UniqueConstraintViolationException`. **backend-dev**.
- **L-4.** `ManualPaymentService`/`MtnMomoPaymentService` "terminal" lists and `OrderStatus` paid-status lists are repeated in four places (`OrderService`, `ReportingService`, two gateways). Put `OrderStatus::paidStatuses()` and `PaymentStatus::terminal()` on the enums. **backend-dev**.
- **L-5.** i18n: only `lang/en/auth.php` and `passwords.php` exist; storefront strings, SMS defaults (`SmsNotificationService.php:117-125`) and emails are hard-coded English, against TOR 7/8.3 "UI copy externalised". **frontend-dev**.
- **L-6.** Phone numbers are stored in free format in `users`, `addresses`, `orders` (`delivery_phone`, `guest_phone`), `payments.payer_phone`; only the MoMo path normalises (`MtnMomoPaymentService::normalizeMsisdn`). Duplicate-account detection, track-order matching and SMS delivery all suffer. Add a `PhoneNumber` normaliser used at every write. **backend-dev**.
- **L-7.** `ReportExportController` is under `/admin/reports/export/...`, a path that overlaps the Filament panel prefix and is outside the panel's auth stack (it re-implements RBAC by hand). Safe today; move to a Filament page action or route under the panel to inherit MFA/session policies. **security-reviewer**.

---

## 2. Missing modules versus the TOR

| TOR item | State | Gap |
|---|---|---|
| 6.5 Refund / credit | Preference stored, nothing executed | H-2 |
| 6.5 Hubtel / MTN live payments | MTN built, dormant; manual live | needs C-1, M-9, M-15 before enabling |
| 6.6 Internal new-order SMS; critical updates in account | Bell only; customer bell exists | M-12 |
| 11 Daily reconciliation report | Missing | M-12 |
| 11 Manual override on stuck orders | Missing | M-12 |
| 11 Outside-zone checkout block | Missing | M-10 |
| 6.4 Auto-Completed after window | Missing | M-12 |
| 9 ReportSnapshot | Missing | M-6 |
| 6.7 Reviews, Q&A, moderation | Tables for reviews only; no routes, no admin, no Q&A table | new module: `reviews` routes + moderation Resource, `product_questions`/`answers` tables, rating aggregation |
| 6.8 Coupons, flash sales, bundles | Coupon model; checkout accepts a code; no admin; no flash-sale/bundle tables | M-1; add `promotions` (type, price rules, starts/ends) and price resolution service |
| 6.9 Wishlist, recently viewed, recommendations | Wishlist table/model only; no routes | new routes + `recently_viewed` (session/cache) + rule-based related rail |
| 6.12 Returns / disputes, support console | Role exists (`support-agent`), no module | `return_requests` (order_item_id, reason, status, resolution, refund_id), `order_messages`; Filament `ReturnRequestResource` |
| 6.2 Typo-tolerant faceted search | LIKE search | M-4 |
| 5.3 Redis, S3 + CDN, search index | Not present | D-1, M-4 |
| 5.4 Staging, CI tests, backups, monitoring | Image publish only | H-8, D-1, D-3 |
| 6.6 Customer notification preferences, push | Missing | `notification_preferences` (user_id, channel, event, enabled); respect in `notify()` |
| 7 Data protection: retention/deletion policy | Missing | scheduled anonymisation of guest PII on old completed orders; user export/delete |
| 6.1 Variants | Dead schema | M-14 |

---

## 3. Recommended target boundaries (no rewrite, just seams)

1. **Payments** (`PaymentService` + gateways + `Refund`): only module that calls `OrderService` for payment outcomes.
2. **Inventory** (`StockService` becomes `InventoryService` over variants): sole writer of `available/reserved` and `stock_movements`; exposes `reserve`, `release`, `consume`, `restock`, `adjust`, and an `audit()`.
3. **Orders** (`OrderService`): state machine, history, emits domain events (`OrderPaid`, `OrderCancelled`, `OrderOnHold`, ...) after commit.
4. **Notifications**: listeners on those events enqueue jobs per channel; `OrderService` no longer imports SMS/email classes.
5. **Promotions** (`PromotionService`): prices, coupons, flash sales, redemptions.
6. **Reporting**: reads snapshots; no business logic.

This keeps the modular monolith (TOR 5.1) but pre-draws the split lines, and each seam is also where the handoff tasks below attach.

---

## 4. Prioritised roadmap with handoff tasks

### Phase A - Stop the bleeding (before any real customer traffic)
1. **backend-dev**: C-1 fail-closed payments (disable sandbox/webhook unless env allows; provider filter in `handleCallback`; production assertion). Default `MOMO_MODE=disabled`.
2. **security-reviewer**: verify C-1 closed; review H-4 limiter values; check `api.php`/zip (L-2).
3. **backend-dev**: C-3 availability check + ordered locks + deadlock retry in `reserveForOrder`.
4. **backend-dev**: C-2 `markPaid` handling of `Cancelled`; cancel pending payments on order cancel; Confirm button gated on order status; `transition()` validates.
5. **backend-dev**: H-4 named rate limiters (login, register, forgot, track, checkout, upload), cap open Pending orders per phone/session, 2 h initial manual hold extending on proof.
6. **backend-dev**: H-3 amount verification for manual confirmation (`payments.amount_received`).
7. **tester**: regression suite for 1-6 against MySQL (add CI MySQL job first - H-8).
8. **doc-writer**: correct DEPLOYMENT.md (simulate warning, env table), replace README.md.

### Phase B - Make it correct and observable (pilot go-live)
1. **backend-dev**: H-1 net-consumed restock + credit-path consume + `cymarket:audit-stock`.
2. **backend-dev**: H-5 queued notifications `afterCommit`, two-phase checkout, SMS retry as job backoff.
3. **backend-dev**: H-7 failed/rejected payment closes the order and reopens the cart (+ order-based sweeper).
4. **backend-dev**: H-6 lazy cart creation, cache menu, prune job.
5. **backend-dev**: M-3 index/constraint migration; M-9 payment row locks and idempotent failure; M-10 required active zone; M-11 private proofs.
6. **backend-dev**: M-12 escalation notifications, auto-complete job, reconciliation report, force-status action.
7. **tester**: H-8 CI with MySQL, publish gated on tests; concurrency test.
8. **backend-dev / doc-writer**: D-2, D-3, D-4 (migrate service, `schedule:work`, required env vars, queue tuning, log rotation), runbook.
9. **security-reviewer**: M-13 `EnsureUserIsActive`, admin MFA decision.

### Phase C - Money correctness and ops resilience (first weeks live)
1. **architect + backend-dev**: H-2 refunds/credits tables, `RefundResource`, `refund()` on the gateway interface; M-1 coupon redemptions; M-2 Money class.
2. **backend-dev**: M-6 report snapshots keyed on `paid_at`, dashboard polling and caching; M-7 Filament eager loading; M-8 settings cache.
3. **backend-dev**: D-1 Redis (session/cache/queue), backups with restore drill, S3-compatible media + CDN, staging environment.
4. **backend-dev**: M-15 `PaymentService` seam and domain events; re-enable MoMo only after C-1, M-9, M-15.

### Phase D - Phase 2 product scope (design first)
1. **architect**: detailed design for M-14 variants-as-stock-unit, promotions/flash sales, reviews + Q&A, returns/disputes, notification preferences, data retention. Do M-14 before real stock and orders accumulate.
2. **backend-dev / frontend-dev**: wishlist, reviews with moderation, coupon admin, flash sales, returns console, FULLTEXT then Meilisearch (M-4), i18n externalisation (L-5), phone normalisation (L-6).
3. **tester / doc-writer**: acceptance scripts for TOR 15.1 and 15.2; admin and Inventory Officer manuals (TOR 12).

---

## 5. Summary ranking (one line each)

| # | ID | Severity | Item |
|---|---|---|---|
| 1 | C-1 | Critical | Unauthenticated simulate/webhook path can mark manual payments paid |
| 2 | C-2 | Critical | Paying or confirming a cancelled order resurrects it and consumes unreserved stock |
| 3 | C-3 | Critical | Reservation never checks availability; lock order can deadlock |
| 4 | H-1 | High | On-Hold cancel restocks stock never deducted; credit path ships ghost stock |
| 5 | H-2 | High | Refund and credit are only log lines |
| 6 | H-3 | High | Manual confirmation does not verify the amount |
| 7 | H-4 | High | No rate limiting; anonymous 24 h stock-holding DoS |
| 8 | H-5 | High | SMS/email/gateway I/O inside row-locking transactions |
| 9 | H-6 | High | A cart row is written on every anonymous request |
| 10 | H-7 | High | Failed/rejected payment leaves a zombie PendingPayment order |
| 11 | H-8 | High | CI runs no tests; races untestable on SQLite |
| 12 | M-1..M-15 | Medium | Coupons, floats, indexes, search, counters, reporting, Filament N+1, settings cache, payment idempotency, zone enforcement, public proofs, escalation, auth, variants, gateway abstraction |
| 13 | D-1..D-4 | Medium | Single-host stateful design, entrypoint races, unsafe fallbacks, queue config |
| 14 | L-1..L-7 | Low | Docs, stray files, retry on unique collisions, enum helpers, i18n, phone normalisation, export route location |
