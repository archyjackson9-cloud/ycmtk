# CY-Market Test Health Audit

Role: Tester. Date: 2026-10-16. Scope: PHPUnit 12 / Laravel 13, PHP 8.3, SQLite in-memory.
No application code or existing tests were changed. Four throwaway probe tests were written to confirm
suspected bugs and then deleted (see section 3).

## 1. Suite results

| Run | Result | Time |
|---|---|---|
| `php artisan test` (MtnMomoLiveTest excluded) | 72 passed, 238 assertions, 0 failures | 103 s |
| `vendor/bin/phpunit` (2nd run) | 72 passed | 46 s |
| `vendor/bin/phpunit` + JUnit (3rd run) | 72 passed | 83 s |

There are no failures. The suite is green, but that is weak evidence: the tests that matter most
(race conditions, replay, late payment, refunds, coupons) are mostly absent, and the probes below show the
code is wrong in exactly those places.

Wall time varies up to 2x between identical runs (46-103 s). Filament boot dominates.

### Slowest tests (JUnit, run 3)

| Time | Test |
|---|---|
| 30.8 s | Admin\PanelAccessTest::test_customers_cannot_access_the_admin_panel |
| 18.2 s | Admin\ReportsTest::test_every_report_page_renders_for_the_roles_allowed_to_see_it |
| 3.8 s | InAppNotificationTest::test_a_guest_order_creates_no_bell_notification_for_anyone_to_read |
| 2.3 s | InAppNotificationTest::test_placing_an_order_notifies_super_admin_and_inventory_officer_but_not_support_agent |
| 2.0-2.2 s | five ManualPaymentTest / OrderNotificationEmailTest checkout-through-HTTP tests |

Per class: PanelAccessTest 31.7 s, ReportsTest 22.0 s, ManualPaymentTest 12.4 s, InAppNotificationTest 8.5 s,
OrderNotificationEmailTest 4.4 s. Everything else is under 1 s.
About 75% of run time is two classes; in PanelAccessTest it is a single test. That test is the first to boot the
Filament panel (cold view/route/Livewire/icon caches), so the cost is first-hit and moves with test order.

## 2. Bottlenecks in testing

1. **Cold Filament boot dominates (about 30 s).** Caches are not warmed for the testing environment. Fix: run
   `php artisan optimize` / `filament:cache-components` in CI before tests, and use `php artisan test --parallel`
   (needs `brianium/paratest`; SQLite memory works per process).
2. **Every checkout test goes through full HTTP, mail, notifications and views (about 2 s each).** Several tests redo
   the add-to-cart -> checkout.store dance (`ManualPaymentTest::placeOrder`). Extract a `placeOrder()` helper into
   `CreatesCymarketFixtures` that calls `CheckoutService` directly; keep only 1-2 true end-to-end HTTP tests.
3. **`makeCustomer()/makeSuperAdmin()/makeInventoryOfficer()` re-run `RolePermissionSeeder` on every call.** Seed once in
   `setUp`.
4. **No Order/OrderItem/Payment/Coupon/DeliveryZone factories** (only Category, Product, User exist).
   `StockReservationTest::pendingOrderFor` hand-builds an order duplicating `CheckoutService`. This is the main reason
   edge-case tests are not being written.
5. **No coverage driver visible** (pcov/xdebug), so coverage is unmeasured. Install pcov in CI and gate on a minimum.
   The map in section 5 is by manual inspection.
6. **SQLite in-memory cannot test concurrency.** `lockForUpdate()` is a no-op there, so no test can prove the locking in
   `StockService`. Race tests need MySQL in CI and two connections/processes. Until then, assert invariants sequentially (as the probes do).
7. **`MtnMomoLiveTest` is in the default suite** and is only avoided by remembering to exclude it. Tag with
   `#[Group('live')]`, skip unless `MOMO_LIVE_TESTS=1`, and exclude the group in phpunit.xml.
8. **Placeholder `ExampleTest` files** (Feature and Unit) add nothing. `tests/Unit` is effectively empty although pure logic exists
   (`Coupon::isValid/discountFor`, `OrderStatus::canTransitionTo`, `normalizeMsisdn`, `ReportPeriod`, `SettingsService`).

## 3. Confirmed by probe (real bugs revealed by missing tests)

Throwaway tests were run against the real services and deleted afterwards; `git status` shows no test changes.

### P1. CRITICAL - A customer can mark their own pay-by-transfer order as paid without staff
- Evidence: unauthenticated `POST /webhooks/mtn-momo {"externalId":"<manual payment reference>","status":"SUCCESSFUL"}` returned 200
  and the order became `paid`.
- Why: `PaymentGatewayInterface` is bound to `MtnMomoPaymentService`; its `handleCallback` looks the payment up by `reference`
  regardless of `provider`. `MOMO_MODE` defaults to `simulate` (`.env` and `.env.example`), and in simulate mode `applyStatus()`
  trusts the payload. The reference (`PAY-YYYYmmddHHMMSS-XXXXXX`) is shown to the customer in their own URL
  (`/checkout/manual-pay/{reference}`). `POST /checkout/sandbox-pay/{reference}` has the same hole (open whenever mode is not
  `live`, and it also accepts manual payments). Manual is the only live payment method, so the production configuration is the vulnerable one.
- Severity: Critical (free goods). Route has no throttle and is CSRF-exempt.
- Fix: require `$payment->provider === 'mtn_momo'` in `handleCallback` and sandbox routes; refuse webhook/sandbox when
  `app()->isProduction()` unless explicitly enabled; default mode must not be `simulate` in production.
- Tests: webhook on a manual reference returns 4xx and the order stays `pending_payment`; sandbox-confirm on a manual reference 404s;
  webhook/sandbox refused under production env.

### P2. HIGH - Overselling: reservation does not check stock
- Evidence: product with 3 units, two pending orders of 3 each: both `reserveForOrder` calls succeeded, `available=3 reserved=6`.
- Why: `StockService::reserveForOrder` takes the row lock but never checks `available - reserved >= qty`. The only check is
  `CartService::validateForCheckout`, run before the transaction and not under lock, so two concurrent checkouts both pass it.
  Customers then pay and land On Hold routinely instead of exceptionally.
- Fix: re-check inside the locked section and throw `CheckoutException` so the transaction rolls back.
- Tests: sequential two-order test (second reserve throws, `reserved_quantity` stays 3); MySQL-only two-process test where exactly one buyer wins the last unit.

### P3. HIGH - Cancelling an On Hold order inflates stock
- Evidence: 3 units; order A paid, order B went On Hold (nothing deducted, avail=0). Cancelling B left `avail=3` (expected 0).
- Why: `OrderService::cancel` treats `OnHold` plus a successful payment as `wasPaid` and calls `restockForOrder`, but On Hold means
  `consumeForOrder` returned false and nothing was deducted. `resolveOnHold` with "refund" takes the same path, so every
  documented refund creates phantom inventory. The order's active reservation is also not released.
- Fix: restock only when an `Out` StockMovement exists for the order; release active reservations when cancelling On Hold.
- Tests: cancel On Hold leaves available unchanged and no active reservations; cancel Paid restocks exactly once.

### P4. HIGH - A late payment resurrects a cancelled order
- Evidence: order cancelled by reservation timeout, then the transfer is confirmed: `cancelled -> paid`, stock 5 -> 3 consumed with no reservation.
- Why: `markPaid` short-circuits only for paid-or-later statuses. `Cancelled` falls through, `transition()` has no `canTransitionTo`
  guard, and `consumeForOrder` deducts. A bank transfer confirmed after the (hours-long) reservation window is a normal event.
- Fix: handle `Cancelled` explicitly in `markPaid` (record payment, flag refund needed, alert staff, keep status); enforce the state machine inside `transition()`.
- Tests: confirming a payment on a cancelled order keeps it cancelled, no `Out` movements, refund-needed signal raised.

## 4. Additional code findings (from reading, not probed)

| # | Finding | Severity |
|---|---|---|
| F1 | `Coupon.used_count` is never incremented anywhere in `app/`, so `usage_limit` can never be reached; no per-customer limit; no atomic check at checkout. A single-use coupon is unlimited. | High |
| F2 | Refunds are log-only: `cancel()` writes a log line for paid orders; `Payment.status` never becomes `Refunded`, no refund record. Reports derive "refunded revenue" from order status, so reports and reality can diverge. | High |
| F3 | `ManualPaymentService` stores `amount_received` but never compares it to `order.total`; an under-payment marks the order fully paid (UI helper text claims it is checked). | Medium |
| F4 | `OrderService::resolveOnHold` does not verify the order is On Hold (only the controller does). `transition()` has no state-machine guard (see P4). | Medium |
| F5 | No `throttle` middleware anywhere (login, register, password reset, `/track-order`, webhook). Order number plus phone is the only secret for guest tracking. | Medium |
| F6 | Webhook is unauthenticated with no signature/IP allow-list; in live mode each callback triggers an outbound MTN request (amplification). Returns 500 on any exception. | Medium |
| F7 | `EscalateStaleOrders` only logs (comment promises SMS). Abandoned-carts, stale-order and reconcile commands have no tests. | Low/Med |
| F8 | `StockService::manualAdjustment` can drive `available_quantity` negative or below `reserved_quantity`. | Medium |
| F9 | Invalid coupon codes are silently ignored at checkout (no feedback), and untested. | Low |
| F10 | A valid signed email link authorizes state-changing POSTs (`cancel`, `resolve-hold`) for anyone holding the link. | Low |

## 5. Coverage map

Legend: C covered, P partial, N none.

### Services
| Unit | Cover | Notes |
|---|---|---|
| CartService | P | add/update/remove covered. Missing: `mergeGuestCartIntoUser`, `validateForCheckout` (inactive/out-of-season product, price drift, quantity clamp), variants, user carts |
| CheckoutService | P | only via manual-payment HTTP tests. Missing: coupon path, zone vs default fee, gateway exception rollback (cart not converted, no order), min order quantity, empty cart, stale-cart issues |
| StockService | P | reserve, expiry, adjustment, one on-hold case. Missing: `releaseForOrder` idempotency, restock of non-consumed order (P3), negative adjustment (F8), null-product items, concurrency, over-limit reserve (P2) |
| OrderService | P | transitions, cancel paid. Missing: `resolveOnHold` (both branches), cancel from every status, `markPaid` on cancelled/on-hold/duplicate (P4), `markPaymentFailed` on non-pending, notification failures |
| ManualPaymentService | P | confirm, reject, terminal no-op. Missing: amount mismatch (F3), double-confirm, confirm after cancel (P4) |
| MtnMomoPaymentService | P | normalise, initiate, rejection, verified webhook, replay. Missing: `expireStale`, `refreshStatus` non-2xx/timeouts, token cache/failure, `referenceId` lookup, simulate abuse (P1), replay of FAILED then SUCCESSFUL |
| PaymentGatewayResolver | N | unknown method, provider mapping |
| SettingsService | N | typed getters, cache invalidation, defaults |
| ReportingService | P | well covered via ReportsTest. Missing: timezone/day boundary, refund semantics (F2) |
| SMS / Email notification services | P | email covered; SMS (TOR-mandatory triggers, failure handling, NotificationLog rows) not covered |

### Flows
| Flow | Cover |
|---|---|
| Guest checkout (manual) | P (happy path) |
| Registered-customer checkout, default address | N |
| Coupon checkout | N |
| MoMo checkout live/simulate over HTTP (sandbox-pay pages) | N (service-level only) |
| Order lifecycle PendingPayment to Completed with timestamps/history | P |
| On Hold resolve (refund/credit) HTTP flow | N |
| Customer cancel HTTP (before and after payment) | N |
| Order tracking by number+phone and signed email link | N |
| Payment proof upload | P (happy path only; no type/size/terminal-state negatives) |
| Cart merge on login | N |
| Register, login, logout, password reset, throttling | N |
| Addresses CRUD, account update | N |
| Product listing/filter, category, home, wishlist, reviews | N |
| Scheduled commands (4) | P (reservation sweep only) |
| PWA, in-app notification bell | C |

### Authorization
- `OrderPolicy`: no direct tests. Missing: view own vs another customer (403), guest without session (403), support_agent can view but not `updateStatus`, inactive staff.
- Storefront: no test that customer A cannot open customer B's `/orders/{id}`, `payment-status`, `cancel`, `resolve-hold`.
- Filament covered: panel access, Product, Category, Reports, NotificationLog, dashboard widgets. Missing per role (super_admin / inventory_officer / support_agent / customer / inactive): OrderResource (view, status and cancel actions), PaymentResource (confirm/reject), StockMovementResource (create adjustment), UserResource and CustomerResource (role escalation, last super-admin, self-demotion), DeliveryZoneResource, BannerResource, Settings page.
- Filament `->visible()` hides buttons but does not block a crafted Livewire call. Add negative `callTableAction`/`callAction` tests as unauthorized roles.

### Edge cases
| Edge case | Status |
|---|---|
| Stock race / last unit | Untestable on SQLite; code unguarded (P2) |
| Replayed webhook | C for SUCCESSFUL; N for FAILED then SUCCESSFUL, out-of-order callbacks |
| Refunds | N (F2) |
| Coupons: expired, not started, min spend, usage limit, percent over 100, fixed over subtotal, inactive, per-user | N (F1) |
| Partial payment / amount mismatch | N (F3) |
| Zero/low stock, minimum order quantity | P |
| Cancel after payment, cancel On Hold | P / N (P3) |
| Money rounding (percent coupons, decimals) | N |
| Day/timezone boundaries in reports | N |
| Upload abuse (type, size boundary) | N |
| Mail/SMS gateway failure must not break checkout | N |

## 6. Ranked remediation (write the failing test first)

1. **P1** webhook/sandbox accepts manual payments in simulate mode (Critical).
2. **P2** reserve without availability check (High); add a MySQL CI job for a real race.
3. **P3** cancel On Hold inflates stock (High).
4. **P4** late payment resurrects a cancelled order (High).
5. **F1** coupon usage never counted (High).
6. **F2** refund tracking (High; needs a design decision from architect/backend-dev).
7. **Authorization matrix test** (cheap, high value): guest / customer A / customer B / support_agent / inventory_officer / super_admin / inactive x each resource, page, action and order route, including Livewire negatives.
8. **F3** amount received vs total; **F8** negative adjustments.
9. **F5/F6** throttling on login, track-order and webhook.
10. **Test infrastructure** (section 2): factories, seed roles once, `placeOrder()` helper, parallel plus cached Filament in CI, pcov gate, MySQL job, `live` group, remove ExampleTests.
11. Scheduled commands and SMS service tests.
12. Storefront breadth: auth, cart merge, addresses, account, listings.

## 7. Proposed test files (not created in this pass)

- `tests/Feature/Payments/PaymentIntegrityTest.php` (P1, F3, P4, replay/flip)
- `tests/Feature/Stock/OversellAndRestockTest.php` (P2, P3, F8)
- `tests/Feature/Checkout/CouponCheckoutTest.php` (F1, rounding) and `CheckoutServiceTest.php` (rollback, zone fee, registered user)
- `tests/Feature/Orders/OrderAuthorizationTest.php`, `tests/Unit/OrderPolicyTest.php`, `OnHoldResolutionTest.php`, `OrderTrackingTest.php`, `CustomerCancelTest.php`
- `tests/Feature/Admin/RoleMatrixTest.php`
- `tests/Feature/Console/ScheduledCommandsTest.php`
- `tests/Feature/Auth/*`
- `tests/Unit/CouponTest.php`, `OrderStatusTest.php`, `SettingsServiceTest.php`, `PaymentGatewayResolverTest.php`
- `tests/Feature/Concurrency/LastUnitRaceTest.php` (MySQL only, `#[Group('mysql')]`)
