# CY-Market Documentation Audit

Date: 2026-10-16. Role: doc-writer (audit only, no existing docs changed).
Method: every claim below was checked against routes, services, enums, Filament resources, config, Docker files and tests. Items I could not confirm are marked TODO-VERIFY.

## 1. Verdict

The documentation is a build diary, not a documentation set. `README.md` is the unmodified Laravel stock README and says nothing about CY-Market. `SETUP.md` and `DEPLOYMENT.md` hold real, useful detail but are written as chronological change notes ("now", "has already been removed", "was kept as-is"), contradict each other and the code in several places, and mix developer, admin and ops audiences on the same page. There are no customer guides, no staff guides, no architecture, data-model, order/payment-flow or webhook documents, no changelog, and no `docs/` folder.

A new developer can get the app running locally only by reading SETUP.md top to bottom and ignoring its stale parts. A new admin has nothing task-based. A customer has nothing (nothing in-app either, beyond the checkout instructions text).

## 2. What exists today

| File | Audience | State |
|---|---|---|
| `README.md` | none | Stock Laravel boilerplate (logo, "Learning Laravel", Laravel security email). Zero CY-Market content. |
| `SETUP.md` | developer (Windows/XAMPP/MySQL) | Detailed, mostly accurate for local run; stale in places (section 3). |
| `DEPLOYMENT.md` | ops (Hostinger VPS, Docker) | Detailed; several statements contradict compose/CI (section 3). |
| `.env.example` | developer | Out of step with `.env.docker.example` and config (section 3). |
| `.env.docker.example` | ops | Mostly good; a few misleading comments. |
| `AGENTS.md` / `CLAUDE.md` | AI agents | `CLAUDE.md` is the Boost bootstrap stub; `AGENTS.md` is generated Boost content. Not human docs; leave as is. |
| `CY-Market_Terms_of_Reference 2.docx` | requirements | Source of intent, but binary and not linked from any doc; code comments cite "TOR section x" constantly with no way to look them up. |
| Code comments | developer | Unusually good: services and controllers carry rationale. The only architecture documentation today; harvest into docs. |

## 3. Inaccurate, outdated or contradictory content (verified)

| # | Doc | Statement | Reality (evidence) | Severity |
|---|---|---|---|---|
| 1 | README | Whole file | Laravel boilerplate; no project name, purpose, stack, run steps, links. | High |
| 2 | SETUP s9, DEPLOYMENT | "Three" scheduled commands | Four are scheduled in `routes/console.php`; `cymarket:reconcile-momo-payments` (every minute) is missing from SETUP's list. | Medium |
| 3 | SETUP s11 | Banners, wishlist, coupons, reviews, variants "scaffolded, no UI"; Marketing Admin "no dedicated screens" | `BannerResource` exists (Marketing group, Super Admin + Marketing Admin) and `BannerSeeder` runs in `DatabaseSeeder`. Reviews are displayed on the product page. Coupons are accepted at checkout (`coupon_code`, `CheckoutService::resolveCoupon`). Still true: no customer review/wishlist submission and no Coupon admin screen. | Medium |
| 4 | SETUP s11 | "Fully built" list | Also built but undocumented: Banners, Customers, Reports (Sales, Customers, Operations, Inventory + CSV export), dashboard widgets. Reports appear nowhere in SETUP/DEPLOYMENT. | Medium |
| 5 | SETUP s3 | "Existing .env ... settings block was appended", "stale composer.lock ... already removed" | Project-history narration, meaningless to a fresh clone. `composer.lock` is present. | Medium |
| 6 | SETUP s3 vs `.env.example` | Says DB is MySQL `cymarket`, root, no password | `.env.example` ships `DB_CONNECTION=sqlite` with MySQL lines commented out. A developer copying it gets SQLite, contradicting the guide. | High |
| 7 | `.env.example`, `.env.docker.example`, `config/momo.php` | MoMo is "the pilot payment method" | `CheckoutController::store()` forces `payment_method = 'manual'`; MoMo is dormant. SETUP/DEPLOYMENT say so; the env files and config headers still say the opposite. | High |
| 8 | SETUP s8 | "Bringing MTN MoMo back": re-add selector to checkout view and controller | Incomplete. `CheckoutService` also reads `momo_number` (needs a form input), callback URL must be public https, and cancelling a paid order does not refund (item 14). No re-enable checklist or test plan. | Medium |
| 9 | DEPLOYMENT intro, s4, "Redeploying" | `docker compose up -d --build`; Dockerfile "built on the VPS" | `docker-compose.yml` uses `image: ${APP_IMAGE:-ghcr.io/archyjackson9-cloud/ycmtk:latest}` with no `build:` key; its header says Docker Manager cannot build from source. `--build` is a no-op. The real path is GitHub Actions (`.github/workflows/docker-publish.yml`: push to `main` publishes `ghcr.io/<owner>/ycmtk:latest` and `:<sha>`), then `docker compose pull && up -d`. The CI workflow is documented nowhere. | High |
| 10 | DEPLOYMENT intro, s7 | Stack is app, scheduler, queue, db; "all four services" | Compose also has `caddy`. Caddy is only mentioned in section 8. | Medium |
| 11 | `.env.docker.example` | `APP_PORT=80` is the host port for the `app` service `ports:` entry | `app` has `expose: 80` only; `APP_PORT` is referenced nowhere in compose. Dead variable; host ports 80/443 belong to `caddy`. | Medium |
| 12 | DEPLOYMENT s5 | `key:generate` in the container, copy the key to `.env` | `exec ... key:generate` writes the container's `.env`, while compose injects `APP_KEY` from the host env; entrypoint runs `config:cache` before a key exists. Fragile and partly misexplained. TODO-VERIFY on a clean VPS. | Medium |
| 13 | DEPLOYMENT s6 | `db:seed --force` "only if you want the demo catalogue/roles" | Roles are only created by `RolePermissionSeeder` (inside `db:seed`). Without seeding nobody can log in to `/admin`. No documented way to create the first Super Admin without the demo seed, which creates well-known accounts with password `password`. | High |
| 14 | SETUP, DEPLOYMENT | Refunds on cancelling a paid order | Not mentioned. `OrderService::cancel()` restocks and only logs "Refund required" to the `momo` channel; the code comment says a real refund call is future work. `PaymentStatus::Refunded` is never set. Staff have no manual refund procedure. | High |
| 15 | DEPLOYMENT "Pay by transfer" | "Admin > Payments", "Admin > Settings" | Real navigation: group `Sales` (Orders, Payments) and group `Administration` (Settings, Reports, Customers, Staff Users, Delivery Zones, Notification Logs). Use "Sales > Payments", "Administration > Settings". | Low |
| 16 | DEPLOYMENT, compose | Queue worker "nothing dispatches to it" | True today (`OrderEventMail` is synchronous; no `ShouldQueue` in `app/`). Nothing tells developers when this changes. | Low |
| 17 | SETUP s7 | Where SMS/email "sent" appear | SMS go to `storage/logs/sms-YYYY-MM-DD.log`; email goes to `laravel.log` with `MAIL_MAILER=log`. Email location not stated. | Low |
| 18 | `config/cymarket.php` comment | Map centre for "Leaflet / OpenStreetMap" | Env files and the admin map use Google Maps (`GOOGLE_MAPS_API_KEY`, `delivery-location-map.blade.php`). No map library reference found in the checkout view itself. TODO-VERIFY which library the checkout picker uses. | Low |
| 19 | SETUP s10 | Test suite description | Omits `PwaTest`, `InAppNotificationTest`, `OrderNotificationEmailTest`, `ManualPaymentTest`, `Admin/ReportsTest`, `Admin/PanelAccessTest`. No single-file run example. CI runs no tests. | Low |
| 20 | SETUP "PWA" | Placed between sections 11 and 12; mentions a "dismissible banner" | Latest commit replaced the banner with a persistent Install App header button. Doc stale and badly placed. | Low |
| 21 | SETUP s6 + DEPLOYMENT s6 | Seeded credentials | Accurate for dev (password `password`), but nothing warns the seed must never run in production, and DEPLOYMENT suggests seeding the VPS. | High |
| 22 | `docker-compose.yml` vs docs | `SITE_DOMAIN` default | Compose defaults to `cy-market.com`; env example says `yourdomain.com`. An unset value makes Caddy request a certificate for that real domain. Not called out. | Medium |
| 23 | Docs vs CI | Image name | Image is `ghcr.io/archyjackson9-cloud/ycmtk`: personal account, name unrelated to the project. Package visibility and pull authentication for a private image are undocumented. | Medium |
| 24 | Env examples vs config | Variables read by config | `CYMARKET_PILOT_ZONE_LAT/LNG`, `CYMARKET_DEFAULT_LOW_STOCK_THRESHOLD`, `MOMO_TIMEOUT`, `MOMO_PENDING_EXPIRY_MINUTES`, `SMS_TIMEOUT` are absent from both example files. No env reference exists. | Medium |
| 25 | Settings page | `internal_alert_phone` | `OrderService::notifyInventoryOfficers()` reads it, but the Settings page has no field for it and no doc mentions it, so On Hold alerts silently only log. | Medium |
| 26 | Logs folder | Hubtel | `storage/logs/hubtel-*.log` shows a former provider; no code or doc mentions it now. Confirm nothing stale remains. | Low |

## 4. Code and security facts the docs must capture

Not doc errors, but gaps a doc-writer cannot paper over. Flagged for architect, backend-dev and security-reviewer.

| Ref | Finding | Evidence |
|---|---|---|
| S-1 | `api.php` (untracked, repo root) is a one-off MTN sandbox setup script with a hard-coded subscription key (value deliberately not repeated here). It is not in `.gitignore` and could be committed or deployed by accident. Its own header says do not commit. | `api.php` top, `git status` |
| S-2 | Payment proofs are stored on the `public` disk (`payment-proofs/`) and served from `/storage/...`; anyone with the URL can read a customer's payment screenshot. | `CheckoutController::manualProofUpload` |
| S-3 | MoMo webhook is unsigned and accepts POST/PUT with no auth. Live mode re-queries MTN, but in simulate mode (the default) `handleCallback` applies the posted status directly, so a caller who knows a payment reference could mark it successful. Dangerous if MoMo is re-enabled with `MOMO_MODE=simulate` in production. | `MtnMomoPaymentService::handleCallback`, `bootstrap/app.php` |
| S-4 | `trustProxies(at: '*')`. Fine behind Caddy only; risky if the app container is ever published directly. | `bootstrap/app.php` |
| S-5 | `.env.example` has `APP_DEBUG=true`; production must always start from `.env.docker.example`. The README should say so. | `.env.example` |
| S-6 | Guest order lookup is order number + phone; guest view is session-based or via signed link. Customer text should explain this. | `OrderController::track`, `authorizeOrderAccess` |
| F-1 | No customer-facing review, wishlist or coupon management exists (no routes). Do not promise them. | `routes/web.php` |
| F-2 | Product variants are not wired: `CartService` stores `product_variant_id => null`; stock is product-level. | `CartService.php` line 48 |
| F-3 | Stale-order escalation only writes warnings to `laravel.log`; it does not alert the Super Admin (the TOR text says it should). | `EscalateStaleOrders` |
| F-4 | `PaymentStatus::Refunded` is declared but nothing sets it; paid-order cancellation has no refund record (item 14). | `OrderService::cancel` |
| F-5 | An unconfirmed transfer order is auto-cancelled (with SMS) after the hold time (default 24 h) by `cymarket:release-expired-reservations`. Rejecting a transfer releases stock but leaves the order in Pending Payment until the sweeper cancels it. Staff need this explained. | `CheckoutService`, `StockService`, `OrderService::markPaymentFailed` |
| F-6 | A customer may cancel only while Pending Payment; otherwise the message says "contact CY-Market", but no contact channel is configured or documented anywhere. | `OrderController::cancel` |

## 5. Documentation bottlenecks

Ranked by how hard they block the person who hits them.

| Rank | Bottleneck | Blocks | Why it blocks | Severity | Proposed doc |
|---|---|---|---|---|---|
| B-1 | README is Laravel boilerplate | Everyone arriving at the repo | No project description, stack, entry points or links; first impression is "wrong repo". | Critical | New `README.md`: overview, stack, quick start, doc map, TOR link. |
| B-2 | No documented way to create the first Super Admin in production | Ops, go-live | Roles exist only via `db:seed`, and the documented seed creates known-password accounts. A deployer must seed demo data into production or improvise with tinker. | Critical | `docs/ops/first-run-and-admin-bootstrap.md` (production seed of roles + settings only, create admin, rotate credentials). Needs a code change (backend-dev). |
| B-3 | Deployment doc describes a flow the repo does not use | Ops | Doc says build on the VPS; reality is a GHCR image built by CI. Following `--build` silently deploys the old image. Private-image auth, tags and rollback missing. | Critical | Rewrite `DEPLOYMENT.md` into `docs/ops/deployment.md` + `docs/ops/ci-cd-and-release.md`. |
| B-4 | No staff/admin user guide | New admin, inventory officer, support agent | Every operational task (confirm a transfer, move an order, handle On Hold, adjust stock, create a product, set zones, add a banner, read reports) is known only from code. | Critical | `docs/admin/` set (section 6). |
| B-5 | Pay-by-transfer operations undocumented | Super Admin, customers | The only live payment method relies on a human confirming transfers, matching `PAY-...` references, handling expiry, rejecting and refunding. Refunds are manual and untracked (F-4). | Critical | `docs/admin/payments-and-refunds.md` + `docs/customer/paying-by-transfer.md`. |
| B-6 | No customer guide or in-site help | Customers | First-time mobile shoppers get no guidance on tracking, cancelling, what "On Hold" means, or installing the app. | High | `docs/customer/` set; consider a short FAQ page on the storefront. |
| B-7 | No order/payment state-flow documentation | Developers, support | State machine, reservation lifecycle, On Hold race handling and idempotency live only in comments across five classes. Changing checkout risks breaking stock accounting. | High | `docs/dev/order-and-payment-flow.md` with state diagram and per-method sequence. |
| B-8 | Webhook contract and MoMo re-enable path undocumented | Developers, integrators | Payload, auth model (none), CSRF exemption, idempotency, response codes and the simulate-mode risk (S-3) exist only in a docblock. | High | `docs/dev/webhooks-mtn-momo.md` + `docs/dev/enabling-momo.md`. |
| B-9 | SETUP.md contradicts `.env.example` and mixes audiences | New developer | SQLite vs MySQL, history narration, stale feature list. Windows/XAMPP only; no Docker local run although CI and production are Linux. | High | `docs/dev/getting-started.md` (+ optional local-Docker page). |
| B-10 | No data-model documentation | Developers, report writers | 23 models and 32 migrations; money fields, status enums, stock columns (`available_quantity`, `reserved_quantity`, `sold_count`), reservations, movements and the key/value settings store are undocumented. | High | `docs/dev/data-model.md` (ERD + field notes). |
| B-11 | No architecture overview | Developers | Service responsibilities, storefront vs admin request flow, settings caching (`rememberForever`), notification channels and log channels are unmapped. | High | `docs/dev/architecture.md`. |
| B-12 | No env-variable reference; examples disagree | Developers, ops | About 60 variables across three files; some in config but not examples (item 24); `APP_PORT` dead (item 11). | Medium | `docs/dev/configuration.md` (var, default, used by, production advice). |
| B-13 | Runbook, backup and monitoring absent | Ops | Nothing on MySQL and `app-storage` backups, log rotation (only `sms`/`momo` rotate daily), health endpoints (`/up`, `/api/health`), certificate renewal, SMS outage handling. | Medium | `docs/ops/runbook.md`. |
| B-14 | Notifications undocumented | Staff, support, developers | Nine events, three channels, templates and placeholders (`{order_number} {total} {address} {reason}`), retry semantics, Notification Logs screen, missing `internal_alert_phone`. | Medium | `docs/admin/notifications.md` + architecture section. |
| B-15 | Roles and permissions matrix absent | Admin, auditor | Five roles; rules scattered across `canViewAny`/`canAccess`. | Medium | `docs/admin/roles-and-permissions.md` (draft in section 7). |
| B-16 | TOR is a binary docx cited as "TOR section x" in code | Developers | Not greppable or linkable; section numbers meaningless to newcomers. | Medium | `docs/product/requirements-trace.md` (TOR section -> feature -> code) or a Markdown export. |
| B-17 | No changelog | Everyone | Required by the doc-writer rules; recent changes (manual payment, PWA, bell notifications, Caddy) exist only in git messages. | Medium | `CHANGELOG.md` seeded from git history. |
| B-18 | No contributing/testing doc | New developer | Test layout, `CreatesCymarketFixtures`, in-memory SQLite, style, branching; CI builds an image but runs no tests. | Medium | `docs/dev/testing-and-contributing.md`. |
| B-19 | Security and data-handling notes missing | Maintainer, auditor | Findings S-1 to S-6; personal data held (phones, addresses, coordinates, payment proofs) and retention. | Medium | `docs/dev/security-and-privacy.md` (after security-reviewer pass). |
| B-20 | PWA behaviour buried in SETUP | Developers, customers | Cache policy, `CACHE_VERSION` bump rule, HTTPS requirement and iOS limits are good but hidden mid-file. | Low | `docs/dev/pwa.md` + customer install page. |
| B-21 | Agent files confuse humans | New developer | `CLAUDE.md` tells readers to install Boost; `.agents/`, `boost.json` unexplained. | Low | One paragraph in the contributing doc; do not edit generated files. |

## 6. Proposed documentation set

Everything under `docs/`, linked from the new README; one audience per page.

```
README.md                                  overview, stack, quick start, doc map
CHANGELOG.md                               seed from git history
docs/
  customer/
    getting-started.md                     browse, search, account, saved addresses
    ordering-and-checkout.md               cart, delivery details, map pin, coupon, fee and total
    paying-by-transfer.md                  account details, order reference, proof upload, how long items are held
    tracking-your-order.md                 logged-in vs guest (number + phone), status meanings, email link
    notifications.md                       SMS, email, bell
    cancellations-and-on-hold.md           what can be cancelled; On Hold choices (refund vs credit)
    install-the-app.md                     PWA install on Android/iOS, offline limits
    faq.md
  admin/
    roles-and-permissions.md
    daily-operations.md                    start-of-day checklist, bell alerts
    orders.md                              Sales > Orders, status moves, cancel, On Hold, location map
    payments-and-refunds.md                Sales > Payments confirm/reject, matching, expiry, manual refunds
    catalogue-and-stock.md                 products, categories, stock movements, low-stock widget
    delivery-and-settings.md               delivery zones, Administration > Settings sections
    marketing-banners.md
    customers-and-staff-users.md
    reports.md                             four reports, date filters, CSV export, RBAC
    notifications.md                       templates, placeholders, logs, failures
  dev/
    architecture.md
    data-model.md
    order-and-payment-flow.md
    webhooks-mtn-momo.md
    enabling-momo.md
    configuration.md
    getting-started.md                     replaces SETUP.md local sections
    testing-and-contributing.md
    pwa.md
    security-and-privacy.md
  ops/
    deployment.md                          replaces DEPLOYMENT.md
    ci-cd-and-release.md
    first-run-and-admin-bootstrap.md
    runbook.md                             backups, logs, scheduler, health checks, troubleshooting
  product/
    requirements-trace.md
```

Notes for the highest-risk pages:

- **order-and-payment-flow.md**: pay-by-transfer sequence: checkout -> `CheckoutService` -> `StockService::reserveForOrder` (hold hours from settings) -> `ManualPaymentService::initiateCheckout` -> staff confirms -> `OrderService::markPaid` -> `StockService::consumeForOrder` -> Paid, or On Hold if stock is short. Status table from `OrderStatus::allowedNextStatuses()` (Pending Payment -> Paid/Cancelled; Paid -> Processing/On Hold/Cancelled; Processing -> Dispatched/On Hold/Cancelled; On Hold -> Processing/Cancelled; Dispatched -> Delivered; Delivered -> Completed). Payment states Pending/Successful/Failed/Refunded/Cancelled (Refunded unused). Idempotency: replaying a confirmation returns the existing result. Four scheduled commands and what each does.
- **webhooks-mtn-momo.md**: `POST|PUT /webhooks/mtn-momo`, CSRF-exempt, no signature; body fields `externalId`, `referenceId`, `status`, `financialTransactionId`; responses 200 `{status: ok}`, 422 `unknown_reference`, 500 `error`; live mode re-queries `GET /collection/v1_0/requesttopay/{id}` and sends `X-Callback-Url`; reconcile job every minute, 10-minute expiry; amounts/currency per environment (sandbox EUR, Ghana GHS); simulate-mode warning (S-3).
- **admin/payments-and-refunds.md**: exact action names "Confirm Payment" (fields "Amount Received (GHS)", "Reference / note (optional)") and "Reject" (required "Reason"). The amount received is stored but not compared with the order total (it is written to `raw_response` without validation); say so. Refunds currently happen outside the system (F-4).

## 7. Draft roles matrix (verified from code)

| Area | Super Admin | Inventory Officer | Marketing Admin | Support Agent | Customer |
|---|---|---|---|---|---|
| Admin panel login (active user, `User::canAccessPanel`) | yes | yes | yes | yes | no |
| Orders: view | yes | yes | no | yes | own, via storefront |
| Orders: status update / cancel actions | yes | yes | no | TODO-VERIFY (Support Agent described as read-only; check action visibility) | cancel own while Pending Payment |
| Payments: view, confirm, reject | yes | no | no | no | no |
| Products: view | yes | yes | no | no | no |
| Products: create, edit, delete | yes | no | no | no | no |
| Stock Movements: view, record | yes | yes | no | no | no |
| Categories, Delivery Zones, Customers, Staff Users, Settings | yes | no | no | no | no |
| Banners | yes | no | yes | no | no |
| Notification Logs | yes | yes | no | no | no |
| Reports: Sales, Customers, Operations | yes | no | no | no | no |
| Reports: Inventory (and Reports landing page) | yes | yes | no | no | no |

Note: Marketing Admin and Support Agent can log in but see very little navigation; Marketing Admin sees only Banners and the dashboard (TODO-VERIFY dashboard widgets' own visibility checks).

## 8. Recommended order of work

1. **Unblocks go-live:** new README; `ops/first-run-and-admin-bootstrap`; rewritten deployment + CI/CD doc; `admin/payments-and-refunds`; `admin/orders`.
2. **Unblocks onboarding:** reconcile `.env.example` / `.env.docker.example`, then `dev/configuration`, `dev/getting-started`, `dev/architecture`, `dev/order-and-payment-flow`, `admin/roles-and-permissions`, customer set.
3. **Then:** data model, webhook + enable-MoMo, runbook, reports, notifications, testing/contributing, security (after security-reviewer pass), requirements trace, changelog.
4. **Code or config items to hand to other agents before the docs can be final:** B-2 (production admin bootstrap command/seed), S-1 (move or delete `api.php`, add to `.gitignore`, rotate that key since it sits in a plain file), S-2 (private disk for payment proofs), S-3 (refuse webhook status in simulate mode when `APP_ENV=production`), F-4 (record refunds), item 25 (`internal_alert_phone` field), item 11 (dead `APP_PORT`), item 9 (decide: CI-only deploy, or add `build:` to compose), CI runs no tests.

## 9. Maintenance rules proposed

- Each change in behaviour updates the matching `docs/` page and `CHANGELOG.md`.
- Env var changes update `docs/dev/configuration.md` and both example files together.
- Use real menu labels (group > item) from the Filament classes, e.g. "Sales > Payments".
- Never place real keys, phone numbers or seeded credentials in production-facing docs; seeded dev credentials live only in `dev/getting-started.md` with a "never in production" warning.
