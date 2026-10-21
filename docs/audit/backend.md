# CY-Market Backend Audit

Role: backend-dev (review only, no application code changed in this pass).
Scope: services (Cart, Checkout, Order, Stock, Reporting, Settings, Payments, Notifications), models, migrations, enums, webhook, console jobs/queue, policies, plus the Filament/controllers that call them.
Line numbers refer to the working tree at the time of review. "Fix" lines are sketches, not patches.

Severity: **Critical** = money/stock/security loss reachable today; **High** = wrong data or outages under realistic use; **Medium** = performance / robustness; **Low** = hygiene.

---

## 0. How the flows work (end to end)

1. **Cart**: `ShareStorefrontData` middleware calls `CartService::currentCart()` on every storefront request (`firstOrCreate` of an `active` cart keyed by user or session id). `addItem/updateQuantity` check `Product::sellable_quantity` (= `available_quantity - reserved_quantity`). `validateForCheckout()` mutates the cart (deletes/clamps lines, refreshes prices) and is also invoked by `GET /cart` and `GET /checkout`.
2. **Checkout** (`CheckoutController::store` -> `CheckoutService::checkout`): validate cart (outside any transaction) -> `DB::transaction` { create Order + OrderItems, `StockService::reserveForOrder` (row-lock product, `reserved_quantity++`, `StockReservation` row, `StockMovement`), mark cart `converted`, gateway `initiateCheckout` (creates `Payment` pending; MoMo does an HTTP call) } -> after commit `OrderService::notifyPlaced` (SMS + email + staff bell, all synchronous).
3. **Payment confirmation**: manual = staff clicks Confirm in `PaymentResource` -> `ManualPaymentService::confirm` -> `handleCallback` -> update `Payment` -> `OrderService::markPaid` (lock order, `StockService::consumeForOrder`, transition Paid, notify x2; stock short = OnHold). MoMo = webhook `/webhooks/mtn-momo` or poll/reconcile -> `MtnMomoPaymentService::handleCallback/refreshStatus/applyStatus` -> `markPaid` / `markPaymentFailed`.
4. **Lifecycle**: `OrderService::updateStatus` (state machine in `OrderStatus::allowedNextStatuses`), `cancel` (release reservation if pending; restock if "was paid"), `resolveOnHold`.
5. **Background**: scheduler (docker `scheduler` service) runs `release-expired-reservations` (1 min), `reconcile-momo-payments` (1 min), `mark-abandoned-carts` / `escalate-stale-orders` (hourly). A `queue:work` container exists but **nothing is ever queued** (no `ShouldQueue`, no `dispatch`).
6. **Reporting**: `ReportingService` is called directly by Filament widgets/pages and `ReportExportController`; no caching.

Deployed default is `MOMO_MODE=simulate`, `SMS_MODE=log`, `MAIL_MAILER=log`, DB-backed cache/session/queue (`.env.docker.example:99,122,57,55,53`). Only "manual" (pay by transfer) is offered to customers (`CheckoutController.php:86`).

---

## 1. Ranked findings

### CRITICAL

#### C1. Anyone can mark any payment "successful" for free (webhook + sandbox routes trust the body in simulate mode)
- `app/Services/Payments/MtnMomoPaymentService.php:101-118` (esp. `:112`, `:117`, `:193-196`), `app/Http/Controllers/Webhooks/MtnMomoWebhookController.php:26`, `app/Http/Controllers/Storefront/CheckoutController.php:131-154`, `routes/web.php` (webhook + `checkout.sandbox-pay` routes), `app/Providers/AppServiceProvider.php:24`, `.env.docker.example:99`.
- Why: `MOMO_MODE` defaults to `simulate`, including in the docker example. In that mode `handleCallback()` calls `applyStatus($payment, $payload)` with the **unauthenticated request body**. The lookup is `Payment::where('reference', externalId)` with **no provider filter**, so it also matches the *manual* payments that are the only ones created today. A customer knows their own reference (it is in the `checkout.manual-pay/{reference}` URL), so `POST /webhooks/mtn-momo {"externalId":"PAY-...","status":"SUCCESSFUL"}` runs `markPaid`: stock is consumed, order becomes Paid, SMS goes out - without any money moving. `GET/POST /checkout/sandbox-pay/{reference}` does the same with no auth/ownership check. The webhook is CSRF-exempt and unthrottled.
- Fix:
  - Hard-gate simulate paths: `abort_unless(app()->environment('local','testing') && config('momo.mode') !== 'live', 404)` in `sandboxPay/sandboxConfirm`; register those routes only in non-production.
  - Webhook: require `$payment->provider === 'mtn_momo'` and `config('momo.mode') === 'live'`; reject (404/403) otherwise. Add a shared-secret / IP allowlist / signed `X-Callback-Url` token, plus `throttle:60,1`.
  - Boot-time guard: if `app()->isProduction() && config('momo.mode') !== 'live'` while any `mtn_momo` route is registered, fail fast (or log critical).
  - Make `MOMO_MODE` default `live`-or-fail in `config/momo.php` for production, not `simulate`.

#### C2. `markPaid` happily "pays" a Cancelled order (resurrects it, deducts stock, no refund path)
- `app/Services/OrderService.php:56-91` (guard at `:63` lists only Paid..Completed), `app/Services/Payments/ManualPaymentService.php:53-81`, `app/Services/StockService.php:202-229`, `app/Services/OrderService.php:162-200`.
- Why: manual orders hold stock for 24h (`manual_payment_reservation_hours`). After that, `releaseExpiredReservations()` cancels the order (`StockService.php:218`) but the `Payment` stays `pending`. Customer cancel (`OrderController::cancel`) has the same effect. Staff can then still click **Confirm** in Admin > Payments; `markPaid` has no `canTransitionTo(Paid)` check, so it consumes stock (possibly stock that other buyers now reserved), transitions Cancelled -> Paid in the history, and notifies the customer "Order confirmed". Same applies to OnHold re-entry (OnHold is not in the idempotency list, so a second confirm re-runs fulfilment side effects).
- Fix: in `markPaid`, after locking the order: `if ($order->status !== PendingPayment) { if Cancelled => record payment as 'paid_after_cancel' (Payment note + staff alert + refund-required log), return; else return $order; }`. Also in `cancel()` mark any `Pending` payments `Cancelled` (inside the same transaction) and in `PaymentResource::isPendingManual` hide Confirm when order is not `PendingPayment`.

#### C3. Overselling is possible: reservation never checks availability
- `app/Services/StockService.php:29-61` (`:40-41`), `app/Services/CheckoutService.php:38-50`, `app/Services/CartService.php:127-166`.
- Why: stock is validated in `validateForCheckout()` **outside** the transaction and without locks; `reserveForOrder()` then does `reserved_quantity += qty` unconditionally. Two customers who both pass validation both reserve; `reserved_quantity` can exceed `available_quantity`. The "soft reservation" therefore protects nothing, and the design degenerates into the On-Hold-after-payment path (customer already paid, then gets a refund/credit prompt). `sellable_quantity` clamps to 0 so the bug is invisible in UI.
- Fix: inside the checkout transaction, in `reserveForOrder`, after `lockForUpdate()`: `if ($product->available_quantity - $product->reserved_quantity < $item->quantity) throw new CheckoutException([...sold out...])`. Lock products in ascending `product_id` order (see H5). Re-run price/availability from locked rows, not from the pre-transaction cart read.

#### C4. Payment is committed and fulfilment may roll back separately -> payment "successful" but order never paid, and it can never be retried
- `app/Services/Payments/ManualPaymentService.php:63-78`, `app/Services/Payments/MtnMomoPaymentService.php:171-183`, `app/Services/OrderService.php:58-90`.
- Why: `Payment::update(status=Successful)` runs **outside** the `DB::transaction` in `markPaid`. If anything in `markPaid` throws (DB deadlock, a notification insert, SMS/email code, `Order::...->first()` returning null), the transaction rolls back stock + order changes while the payment row stays `Successful`. Every retry then hits `if ($this->isTerminal($payment)) return $payment;` (`ManualPaymentService.php:57`, `MtnMomoPaymentService.php:108`) and nothing ever heals it. Money is taken (or recorded as taken) with no fulfilment.
- Fix: one transaction wrapping `Payment::whereKey()->lockForUpdate()` -> status update -> `markPaid`; make `markPaid` the only writer of both. Move notifications out of the transaction (`DB::afterCommit`, or queued jobs, see H3). Add a reconciliation query (`payments.status=successful AND orders.status=pending_payment`) to the scheduler/admin dashboard.

---

### HIGH

#### H1. OnHold flow corrupts inventory (stock is never deducted, and cancelling restocks stock that was never deducted)
- `app/Services/OrderService.php:69-76`, `:171-178`, `:206-217`; `app/Services/StockService.php:100-146`, `:153-179`, `:202-229`.
- Why: when `consumeForOrder` returns false the reservation stays `active` and nothing is deducted. (a) Customer picks "credit": `resolveOnHold` moves OnHold -> Processing with **no stock deduction** (and no `sold_count`), so on-hand overstates forever. (b) Customer/staff cancels an OnHold order: `$wasPaid` is true (`:171` includes `OnHold` and payment is Successful) so `restockForOrder` **adds** stock that was never removed -> phantom inventory. (c) The reservation sweeper later "releases" the OnHold order's reservation (`:222`) which is fine but unrelated to either branch.
- Fix: track whether stock was consumed (`orders.stock_consumed_at` or a `consumed` reservation status) and only restock when it was. For "credit", re-run `consumeForOrder` when the officer resumes (or block Processing until it succeeds). Add tests: hold -> credit; hold -> refund; assert `available_quantity` and movements.

#### H2. External HTTP call (MTN) and row locks held inside the checkout DB transaction
- `app/Services/CheckoutService.php:50-111` (`:100` locks products, `:108` gateway call), `app/Services/Payments/MtnMomoPaymentService.php:71-72` (30s timeout, `config/momo.php:41`).
- Why: product rows (locked by `reserveForOrder`) and the open transaction stay held for the duration of the MTN call; a slow gateway serialises all checkouts for popular products and can exhaust the DB connection pool. The "roll back if MTN rejects" comment (`:104-107`) is the reason, but a compensating cancel is safer than a long lock.
- Fix: commit order + reservation first, then call the gateway outside the transaction; on rejection call `OrderService::cancel` (or `markPaymentFailed`) to release. Dormant while only manual is offered, but must be fixed before MoMo returns.

#### H3. SMS/email/bell notifications run synchronously, inside request threads and inside DB transactions
- `app/Services/OrderService.php:72,73,86,87,110,151,195,252-259`; `app/Services/Notifications/SmsNotificationService.php:53-80` (up to `sms.retry_attempts`=3 x `sms.timeout`=15s with no backoff); `app/Services/Notifications/EmailNotificationService.php:54-61`; `app/Services/CheckoutService.php:113`; `app/Mail/OrderEventMail.php` (deliberately not queued).
- Why: `markPaid`, `cancel`, `updateStatus`, `markPaymentFailed` call `notify()` while holding `lockForUpdate` on orders and products. A dead SMS gateway = up to ~45 s per event, per order, with locks held, in the admin click or the customer request. SMTP default timeout is 60 s. A checkout request also blocks after commit on SMS + email + staff notify. If a notification throws after commit in `checkout()` the customer sees a 500 though the order exists (`CheckoutService.php:113`).
- Fix: `SendOrderSms`, `SendOrderEmail` queued jobs (`ShouldQueue`, `afterCommit()`, `tries=3`, `backoff=[10,60,300]`) that create/update the `NotificationLog`. The `queue` container already exists in `docker-compose.yml:177`. Replace the in-process retry loop with queue retries. Wrap `notifyPlaced` in try/catch at minimum. Dispatch via `DB::afterCommit` so a rollback never sends a message.

#### H4. Failed / rejected payments leave zombie PendingPayment orders and a false message
- `app/Services/OrderService.php:101-114`, `app/Services/Payments/ManualPaymentService.php:74-78`, `app/Services/Payments/MtnMomoPaymentService.php:181`, `app/Services/Notifications/EmailNotificationService.php:84`, `SmsNotificationService.php:125`, `app/Models/Order.php` (no retry route).
- Why: `markPaymentFailed` releases the reservation but leaves the order `PendingPayment`. The sweeper only acts on *active* reservations (`StockService.php:204`), so the order is never cancelled; it sits in "Orders awaiting action" forever (`StatsOverviewWidget.php:39`). The customer message says "Your cart is still saved - try again", but the cart was set to `converted` (`CheckoutService.php:102`) and there is no "retry payment" endpoint. It also notifies even when the order is already Paid/Cancelled (duplicate/late failure callbacks).
- Fix: on failure/rejection either (a) cancel the order (`cancel($order, 'Payment failed', notify:false)`) and send the failure message, or (b) implement retry (new Payment + new reservation on the same order). Reinstate the cart on cancel (`cart->update(status:'active')` + re-add lines) if the message promise is kept. Only notify when order status is `PendingPayment`.

#### H5. Lock ordering can deadlock concurrent checkouts / payment confirmations
- `app/Services/StockService.php:33-40`, `:102-127`, `:155-161`.
- Why: products are locked in cart/order-item order. Order A (p1,p2) and order B (p2,p1) deadlock under InnoDB. `consumeForOrder` also locks each product twice (two loops) and `releaseForOrder` locks per reservation.
- Fix: `$items = $order->items->sortBy('product_id')`; do a single `Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id')` then loop. Retry transaction on deadlock: `DB::transaction($cb, attempts: 3)`.

#### H6. SMS templates silently become empty strings (Settings page poisons the cache)
- `app/Services/SettingsService.php:14-21`, `:102-105`; `app/Filament/Pages/Settings.php:42`, `:144-147`; `database/seeders/SettingSeeder.php` (no `sms_template_*` rows).
- Why: `Settings::mount()` calls `smsTemplate($event, '')` for every event. `get()` does `Cache::rememberForever('setting:sms_template_x', fn => $value ?? $default)` and caches the **empty-string default** for each missing row. From then on `SmsNotificationService::buildMessage` (`:128`) gets `''` instead of its real default text, so SMS bodies are empty until the cache is cleared. `save()` then writes `''` for every untouched template permanently (`Settings.php:146`), and `$value ?? $default` treats `''` as set.
- Fix: never cache defaults (cache only found DB rows, or cache a sentinel and apply `$default` after the cache read); treat blank as unset (`filled($value) ? $value : $default`); in `Settings::mount()` show the built-in defaults, and in `save()` skip keys equal to default/blank (or delete the row). Seed the defaults.

#### H7. Late MoMo success after local expiry is lost (money taken, order cancelled)
- `app/Services/Payments/MtnMomoPaymentService.php:108-110`, `:148-159`.
- Why: `expireStale` marks a still-pending payment `Failed` after `pending_expiry_minutes`. If MTN later reports SUCCESSFUL (or a delayed callback arrives), `handleCallback` returns early because `Failed` is terminal. No refund, no order, no alert.
- Fix: don't treat locally-expired as `Failed`; use a distinct `expired` status that remains reconcilable, and let a later SUCCESSFUL from MTN re-open (or at minimum alert + refund-log). Compare MTN `amount`/`currency` with `payment->amount` before accepting success.

#### H8. Webhook / callback processing is not idempotent under concurrency
- `app/Services/Payments/MtnMomoPaymentService.php:101-186` (`:108` check-then-act on a stale model, `:161-186` no locking), `ManualPaymentService.php:55-81`, `app/Services/OrderService.php:101-114`.
- Why: callback + order-page poll (`OrderController.php:47-61`) + reconcile job can all read the payment as `Pending` and each run `applyStatus`. `markPaid` survives thanks to the order lock/guard, but `markPaymentFailed` has no guard: each call re-sends the failure SMS/email/bell. Two staff clicking Confirm on the same manual payment is the same hazard.
- Fix: `DB::transaction(fn() => $p = Payment::whereKey($id)->lockForUpdate()->first(); if terminal return; ...)`; give `markPaymentFailed` the same "only act if order is PendingPayment and not already notified" guard; unique key `(order_id, event)` on `notification_logs` or a `Cache::lock` per payment as belt and braces.

#### H9. `GET /cart`-style writes + a cart/session row created for every anonymous request (bots included)
- `app/Http/Middleware/ShareStorefrontData.php:22-37`, `app/Services/CartService.php:18-31`, `app/Console/Commands/MarkAbandonedCarts.php:25-27`.
- Why: every storefront request (crawlers, health probes, assets via rewrite) runs: mega-menu query (uncached), `Cart::firstOrCreate` (INSERT for each new session), `SUM(quantity)` query, and for users 2 notification queries. Rows accumulate: `MarkAbandonedCarts` only flips `status`, never deletes, and sessions are DB-backed. Also: once a cart is marked `abandoned` after 48 h, `currentCart()` only looks for `active`, so a returning customer gets a new **empty** cart and the old items are stranded (contradicts the command's docblock).
- Fix: create the cart lazily (on add-to-cart); in the middleware use `Cart::where(...)->first()` and a single count query, cache the category tree (`Cache::remember('menu', 300, ...)`); in `currentCart`, reactivate the latest `abandoned` cart for the user/session; prune abandoned/empty carts and old sessions on a schedule (`model:prune`).

#### H10. Checkout is not idempotent (double submit creates two orders / two reservations)
- `app/Services/CheckoutService.php:36-50`, `:102`.
- Why: the cart row is not locked and not re-read inside the transaction; two concurrent `POST /checkout` both see an active cart with items and both create orders and reservations. `cart->update(status=converted)` happens after the fact.
- Fix: inside the transaction `Cart::whereKey($cart->id)->where('status','active')->lockForUpdate()->firstOrFail()` and load items from that locked cart; add a hidden idempotency token stored on the order (`unique`) so refresh/double-click returns the existing order.

---

### MEDIUM

#### M1. Manual payment amount is never verified
- `app/Filament/Resources/PaymentResource.php:106-114`, `ManualPaymentService.php:67-70`.
- `amount_received` is only written into `raw_response`; an underpayment of any size can be confirmed and the order marked paid. Fix: require `amount_received >= payment->amount` (or force a typed override reason and store `amount_received` in a column), and show a warning/diff in the modal. Also record the confirming user (already stored) and prevent confirm when order is not PendingPayment (see C2).

#### M2. Stock adjustment can underflow / go below reserved, and fetches the wrong movement
- `app/Services/StockService.php:181-196`, `app/Filament/Resources/StockMovementResource/Pages/CreateStockMovement.php:30`, `database/migrations/2026_01_01_000004_create_products_table.php:18-19` (UNSIGNED).
- A negative delta larger than `available_quantity` raises a DB out-of-range error (HTTP 500 / unhandled) instead of a validation message, and a smaller negative can drop on-hand below `reserved_quantity`. `latest()->firstOrFail()` orders by `created_at` (1 s resolution) - use the model returned by the service or `latest('id')`. Fix: validate inside the lock (`available_quantity + delta >= reserved_quantity`, else throw a validation exception); have `manualAdjustment` return the `StockMovement`.

#### M3. Products' on-hand quantity is directly editable, bypassing the audit trail
- `app/Filament/Resources/ProductResource.php:85` (also create form).
- Violates "never mutate stock columns ad hoc"; no `StockMovement` row, no lock. Fix: make the field create-only (initial stock written via `StockService::manualAdjustment`), `disabled()` on edit.

#### M4. Reservation sweeper can be aborted by one bad order and does per-order sequential work
- `app/Services/StockService.php:202-229` (`:218` can throw `RuntimeException`; HTTP/SMS inside `cancel`).
- One exception stops the loop and repeats every minute; ordering is by group not by expiry. Fix: try/catch per order with `report($e)`, `chunkById`, lock the reservation row (`status=active` re-check) before acting, and queue notifications (H3). Also mark pending manual `Payment` as `Cancelled` on expiry (C2).

#### M5. Reporting counts the wrong timestamp/status and is expensive
- `app/Services/ReportingService.php:31-34`, `:56-80`, `:96-103`, `:120-127`, `:131-141`, `:404-440`.
  - Revenue is bucketed by `orders.created_at`, not `paid_at`; orders `OnHold` (money received) are excluded from `paidStatuses()`.
  - `salesSummary` issues 5 queries (`sum`, `sum`, `count`, `sum`, `avg`) and is re-run by `operationsReport`, `StatsOverviewWidget.php:44`, each report widget and the CSV export - one conditional aggregate would do (`SUM(CASE ...)`).
  - `salesByPeriod` loops day by day for any custom range (`:106`) - a 10-year range = 3.6k iterations/objects; cap the range or `generate_series`/join a calendar.
  - `fulfilmentPerformance` hydrates every order in range to average timestamps in PHP (`:416-428`); use `AVG(TIMESTAMPDIFF(...))` with a driver switch or `selectRaw` per driver.
  - `salesByCategory` inner-joins products (drops lines whose product was hard-removed; `order_items.product_id` is `nullOnDelete`) and `salesByProduct` groups by name/SKU text, so a renamed product splits into two rows. Group by `product_id`.
  - `inventory_valuation` multiplies by `selling_price` (a retail-value figure, not cost).
  - No caching anywhere; the dashboard (`StatsOverviewWidget`) fires about 8 queries per load, every 30 s if polling.
- Fix: one cached (60-120 s, tagged/forgotten on order status change) summary method; `paid_at` for revenue; add the indexes in section 3.

#### M6. N+1 and unbounded loads
- `app/Filament/Resources/OrderResource.php:69-71` - `customerName()` lazy-loads `user` per row (add `->modifyQueryUsing(fn($q)=>$q->with('user'))`).
- `app/Services/CartService.php:99-116` - `mergeGuestCartIntoUser` lazy-loads `items` and `product` per line and is not transactional; wrap in a transaction and `->with('items.product')`.
- `app/Models/Order.php:82-85` - `latestPayment()` loads **all** payments and sorts in PHP; use `payments()->latest('id')->first()` (or a `latestOfMany` relation) - hit by `NewOrderPlaced`, `OrderController::paymentStatus`.
- `app/Http/Controllers/Storefront/ProductController.php:74` - `reviews.user` unbounded; paginate/limit reviews.
- `app/Filament/Widgets/LowStockProductsWidget.php:38` - runs the low-stock query, plucks ids, then queries again; pass the query builder directly.
- `app/Services/ReportingService.php:209-217` - `stockLevels(null)` for the CSV loads the whole catalogue; use `cursor()`/`lazyById()`.
- Notifications: `OrderService::notify` calls `$order->fresh()` up to 3x per transition and re-reads `$order->user` each time; pass the loaded model.

#### M7. Missing indexes (see section 3 for DDL)
Hot, unindexed or poorly-indexed predicates: `payments.order_id/status/provider`, `stock_reservations(order_id,status)`, `carts(user_id,status)` and `(session_id,status)`, `carts(status,updated_at)`, `orders(user_id,status)`, `orders(status,updated_at)`, `orders.paid_at`, `orders.delivery_phone/guest_phone`, `products(is_active,sold_count)` / `(is_active,created_at)`.

#### M8. Duplicate active carts and duplicate cart lines (race + NULL-in-unique)
- `app/Services/CartService.php:21-30`, `:45-49`; `database/migrations/2026_01_01_000011_create_cart_items_table.php:14`.
- `firstOrCreate` has no unique constraint behind it (two parallel first requests = two active carts; `User::cart()` already compensates with `latestOfMany`). `unique(cart_id, product_id, product_variant_id)` does not constrain rows where `product_variant_id IS NULL` (always the case here) on MySQL/SQLite, so `firstOrNew + save` can create duplicate lines. Fix: new migration adding a generated/COALESCE unique key (MySQL: unique on `(cart_id, product_id, coalesce(product_variant_id,0))` via virtual column) or drop the variant column from the key; and `lockForUpdate` the cart in `addItem`. `addItem` also reads stock without any lock (fine as a UX check once C3 is enforced at reserve time).

#### M9. Coupons: usage never counted, no lock, invalid code silently ignored, zone not validated
- `app/Services/CheckoutService.php:55`, `:121-134`; `app/Models/Coupon.php:45` (reads `used_count`, nothing ever increments it); `CheckoutController.php:69`, `CheckoutService.php:52`.
- `usage_limit` is therefore unenforceable; a typo'd code gives no feedback; lookup is case-sensitive; `DeliveryZone::find()` does not check `is_active` (inactive zones - and their fee - are selectable by posting an id). Fix: inside the transaction `Coupon::where(...)->lockForUpdate()`, increment `used_count` (and decrement on cancel), throw `CheckoutException` for invalid codes, `strtoupper` on both sides, use `Rule::exists('delivery_zones','id')->where('is_active',1)`.

#### M10. Money is handled as `float`
- `app/Models/Cart.php:35-38`, `CartItem.php:35-38`, `CheckoutService.php:73`, `Coupon::discountFor`, `Product` casts.
- `float` arithmetic on currency (percentage discounts, `subtotal + fee - discount`) causes cent drift and equality bugs (`CartService.php:160` compares floats with `!==`). Fix: compute in integer pesewas or `bcmath` (string decimals) and round once, half-up, at the line level.

#### M11. Payment status poll hits the MTN API on every request, unthrottled
- `app/Http/Controllers/Storefront/OrderController.php:47-61`, `MtnMomoPaymentService::refreshStatus`.
- Each poll from each browser tab = a live MTN GET (plus token fetch). Fix: `throttle:30,1`, cache the result for ~5 s per payment (`Cache::remember`), and let only the reconciler/webhook talk to MTN once real traffic exists.

#### M12. Order tracking / sensitive endpoints are unthrottled; signed links never expire
- `routes/web.php` (`orders.track`, login, register, forgot-password, webhook, proof upload), `app/Http/Controllers/Storefront/OrderController.php:102-123`, `app/Mail/OrderEventMail.php:40`, `app/Http/Controllers/Auth/AuthenticatedSessionController.php:28`.
- Order number (`CYM-Ymd-XXXXX`, `Order.php:61`) + phone is the only secret for guest tracking and has no rate limit; `Auth::attempt` has no `RateLimiter`/`throttle` (credential stuffing). `URL::signedRoute` links to a page with name/address/phone never expire. Fix: `throttle:5,1` on track and login, `temporarySignedRoute(..., now()->addDays(14))`, normalise phone before comparing (`MtnMomoPaymentService::normalizeMsisdn`), and use a longer random order number or a separate public token.

#### M13. Proof-of-payment upload: public disk, no auth beyond a guessable-ish reference
- `app/Http/Controllers/Storefront/CheckoutController.php:182-198`.
- Files go to the `public` disk (world-readable once the path is known), the reference is the only credential, previous proof is orphaned on re-upload, no throttle. Fix: store on a private disk and stream through an authorised admin route, delete the old file, `throttle`, verify the order belongs to the session/user (`guest_order_ids`/policy).

#### M14. Settings are read through the cache table on every call, and defaults are cached forever
- `app/Services/SettingsService.php:16-20`, `.env*` (`CACHE_STORE=database`).
- Each `get()` is one cache-table query per call (several per request); with `rememberForever` a changed `config('cymarket.*')` default never takes effect after first read. `Setting::saved` and `SettingsService::set` both `forget` (duplicate). Fix: load all settings once per request (`Cache::remember('settings.all', ...)` as one array, or a request-scoped singleton) and invalidate in the model hook only; use Redis for cache/session/queue in production (DB-backed trio contends on the same DB as orders).

#### M15. Root-level `api.php` and `route:cache` closure
- `api.php` (untracked, project root): a one-off MTN sandbox setup script sitting in the web root of a XAMPP/htdocs deployment. If the project root is ever served directly it is executable over HTTP and it handles API credentials. Do not commit it; move it under `scripts/` or delete it after use.
- `routes/api.php:10` uses a closure route while `docker/entrypoint.sh` runs `php artisan route:cache` under `set -e`; closures cannot be serialised, so the container start can fail. Verify, and switch to a controller or invokable class (Laravel already exposes `/up`).

#### M16. Orders can be hard-deleted through DB cascades; no soft deletes on financial rows
- `database/migrations/2026_01_01_000013_create_order_items_table.php:8`, `..._000014_create_payments_table.php:8`, `..._000018_...:8`, `..._000016_...:9`.
- UI blocks deleting orders (`OrderResource::canDelete`), but `orders` -> `payments`/`order_items`/`status_histories`/`stock_reservations` are `cascadeOnDelete`. Any `Order::delete()` (tinker, future code, cascade from user hard-delete is `nullOnDelete` so OK) wipes the ledger. Fix: `restrictOnDelete` for `payments`, add `SoftDeletes` to `Order`.

---

### LOW / hygiene

- **L1. Dead or incomplete features**
  - `OrderService::notifyInventoryOfficers` reads `internal_alert_phone` (`OrderService.php:236`) which has no Settings field and no seed, so the on-hold SMS to staff never fires. `EscalateStaleOrders` (`:31-35`) only logs (docblock promises SMS) and re-logs the same orders every hour.
  - `ProductVariant`, `cart_items.product_variant_id`, `stock_reservations.cart_id`, `Cart::reservations`, `Cart.expires_at`, `Cart.coupon_code`, `Order::scopeStatus`, `OrderPolicy::updateStatus/cancel` (never called; `OrderController` uses `authorize('view')` for cancel) are unused. Either wire them or remove them.
  - `StockMovementType`/`NotificationStatus::Delivered` partially unused.
- **L2. Duplicated logic**
  - `isTerminal()` copy-pasted in `MtnMomoPaymentService.php:188` and `ManualPaymentService.php:107` -> `PaymentStatus::isTerminal()`.
  - Order-event wording exists three times: `SmsNotificationService::buildMessage`, `EmailNotificationService::buildMessage`, `OrderStatusUpdated::message` -> one `OrderEventType::message(Order)` / translation file.
  - Role-name checks repeated inline (`OrderResource.php:128,173`, widgets, Settings) instead of policies/permissions (backend-dev rule 5); `PaymentGatewayInterface` is bound to MoMo in `AppServiceProvider.php:24` while `PaymentGatewayResolver` already exists - the webhook and sandbox should resolve explicitly by provider.
  - `CheckoutService` mixes constructor injection and `app(SettingsService::class)` (`:53,97`).
- **L3. Error handling gaps**
  - `Order::whereKey()->lockForUpdate()->first()` is dereferenced without null checks (`OrderService.php:59,104,123,165`).
  - `OrderResource` actions catch only `RuntimeException` (`:141,182`); DB errors surface as raw 500s.
  - Webhook returns 500 for any `Throwable` including permanent business errors (`MtnMomoWebhookController.php:33-37`) -> MTN retries forever; distinguish retryable vs permanent.
  - `MtnMomoPaymentService::initiateCheckout` marks the payment `Failed` (`:78,86`) inside a transaction that is rolled back anyway, so the failure/raw response is lost from the DB (log only). Also `RuntimeException`-catch-then-rethrow (`:82-83`) is redundant.
  - `OrderEventMail`/email log `attempts` hard-coded 1; `NotificationLog` never reaches `Delivered`.
  - `OrderService::cancel` logs "Refund required" but there is no record/queue of refunds owed (`:183-192`); persist a `refunds` row or `Payment` status `refund_pending`.
- **L4. Soft-deleted users keep unique email/phone** (`RegisteredUserController.php:28-29` `unique:users` counts trashed rows) - decide policy (`Rule::unique()->whereNull('deleted_at')` + DB partial index, or anonymise on delete).
- **L5. `Product::increment('views_count')` on every GET** (`ProductController.php:73`) - a write (and row lock) per page view/bot hit on the hottest row; buffer in cache and flush from the scheduler.
- **L6. Search is `LIKE '%x%'` on `name`, `description` (TEXT), `sku` plus `orWhereHas(category)`** (`Product.php:158-172`) - full scans; add FULLTEXT (MySQL) or limit to name/sku and a category join; cache the home page rails (`HomeController.php:24-30`).
- **L7. Category show doesn't check `is_active`** (`ProductController.php:25`); inactive categories remain browsable by slug.
- **L8. `validateForCheckout` message bug**: `"{$item->product?->name} is no longer available"` prints an empty name when `product` is null (`CartService.php:135`), and the method mutates data from `GET` routes (`CartController.php:22`, `CheckoutController.php:43`) - acceptable, but document it or make it a POST/side-effect-free preview.
- **L9. `Payment::generateReference` / `Order::generateOrderNumber`** use exists()+insert (TOCTOU); the unique index saves integrity but throws a 500 on the (very rare) collision - retry on `UniqueConstraintViolationException`.
- **L10. Legacy schema residue**: `payments.provider` migration default was `hubtel` then switched; `2026_09_24` uses `renameColumn` + `->change()` on a populated table (fine on MySQL 8, but test the `down()` on SQLite). Add a data check so rows with `provider='hubtel'` are normalised.
- **L11. Tests**: there are no concurrency/idempotency tests. Add: parallel reserve of last unit (C3), confirm-after-cancel (C2), webhook replay twice (H8), OnHold->credit/refund stock (H1), SMS template default after Settings mount (H6), simulate-mode webhook forgery rejected in production env (C1).

---

## 2. Suggested order of work

1. **C1** (security hole, small change), **C2**, **C4** (single payment/order transaction), **C3** (reservation check).
2. **H1**, **H4**, **H6** (data-corruption / user-visible).
3. **H3 + H2** (notifications to queue, gateway call out of the transaction) - unlocks perf and removes lock-hold risk.
4. **H5, H8, H10** (locking discipline, idempotency).
5. Indexes migration (section 3), reporting caching (M5), N+1 fixes (M6).
6. Remaining Medium/Low hygiene, dead-code cleanup, tests (L11).

## 3. Proposed new migration(s) (do not edit applied ones)

```php
// 2026_xx_xx_add_performance_indexes.php  (reversible)
Schema::table('payments', function (Blueprint $t) {
    $t->index(['order_id', 'status']);
    $t->index(['provider', 'status', 'created_at']);      // ReconcileMomoPayments
});
Schema::table('stock_reservations', fn ($t) => $t->index(['order_id', 'status']));
Schema::table('carts', function (Blueprint $t) {
    $t->index(['user_id', 'status']);
    $t->index(['session_id', 'status']);
    $t->index(['status', 'updated_at']);                 // MarkAbandonedCarts
});
Schema::table('orders', function (Blueprint $t) {
    $t->index(['user_id', 'status']);
    $t->index(['status', 'updated_at']);                 // EscalateStaleOrders
    $t->index('paid_at');                                // reporting by paid_at
    $t->index('delivery_phone');                         // track-order
    $t->index('guest_phone');
});
Schema::table('products', function (Blueprint $t) {
    $t->index(['is_active', 'sold_count']);              // trending/best-selling
    $t->index(['is_active', 'created_at']);              // newest
});
// Optional: orders.stock_consumed_at (H1), payments.amount_received / expired status (M1, H7),
// notification_logs unique (notifiable_type, notifiable_id, type, channel) (H8),
// orders.checkout_token unique (H10).
```

## 4. What looked good (keep)
- Order and product rows are locked with `lockForUpdate` in most mutating paths; `markPaid` has a decent status-based idempotency guard for the Paid..Completed range.
- Status machine and history are centralised in `OrderStatus` / `OrderService::transition`.
- Live-mode MoMo callback re-queries MTN rather than trusting the body (`MtnMomoPaymentService.php:112-115`); X-Reference-Id is used as the idempotency key.
- CSV export neutralises formula injection (`ReportExportController.php:111-120`) and gates by role.
- Unique indexes on `orders.order_number`, `payments.reference`, `payments.provider_reference`, `products.slug/sku` back the generators.
