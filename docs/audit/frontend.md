# CY-Market Frontend / UI Audit

Role: frontend-dev (review pass only, no application code changed).
Scope: Filament 3.3.55 admin (resources, pages, widgets, reports), Blade + Tailwind 4 storefront, PWA (manifest + service worker), email, build output.
Audience assumed: Ghanaian shoppers on mid-range Android phones with patchy data; non-technical shop staff.
Method: static read of every Filament resource/page/widget, `ReportingService`, all storefront views/layouts, `sw.js`, manifest, `admin-glass.css`, `vite.config.js`, build output in `public/build`, and the uploaded media on disk. Nothing was run in a browser; items marked "verify" need a device or browser check.

Severity: Critical = breaks a core journey or burns real resources constantly; High = clearly hurts conversion, staff throughput or the low-bandwidth audience; Medium = noticeable friction or debt; Low = polish.

---

## 1. Journeys examined

| Journey | Path | Overall |
|---|---|---|
| Browse | `/` -> `/shop` / `/categories/{slug}` -> `/products/{slug}` | Visually rich, but heavy for the audience and weak on mobile navigation (S-01, S-02, S-05) |
| Cart | `/cart` | Works; full-page reload per tap, hidden stock/validation info (S-09, S-10) |
| Checkout | `/checkout` (single page, guest or user) | Total unknown until after order is placed; form not mobile-safe (S-06, S-07, S-08) |
| Pay | `/checkout/manual-pay/{ref}` (pay by transfer) | Missing deadline and copy buttons (S-11, S-12) |
| Track | `/track-order`, `/orders/{id}`, `/my-orders` | Functional; label and a11y issues (S-13, S-14) |
| Reviews | product page | Display only. There is no way to submit a review anywhere in the storefront (S-15) |
| Admin order handling | Orders list -> View -> Update status / Cancel; Payments -> Confirm / Reject | No queue, no badges, payment confirmation is not reachable from the order (A-03, A-04, A-05) |
| Admin stock | Products, Stock Movements, Low Stock widget, Inventory report | Mostly sound; ordering bug in widget, truncated report tables (A-08, A-09) |
| Admin reports | Reports hub + Sales / Inventory / Customers / Operations | Heavy duplicated queries, polling every 5s (A-01, A-02, A-06) |

---

## 2. Ranked summary (top items)

| # | ID | Sev | Area | One line |
|---|---|---|---|---|
| 1 | A-01 | Critical | Admin perf | Stats and chart widgets poll every 5s by default (Filament `CanPoll`), each poll runs ~15 queries / loads order rows into PHP |
| 2 | S-01 | Critical | Storefront mobile | On phones a guest has no Log in / Sign up link and a logged-in user has no Account / Log out in the header |
| 3 | S-06 | High | Checkout | Delivery fee and total are never shown before "Place Order" (always "Calculated on confirmation"); coupon gives no feedback |
| 4 | S-03 | High | Low bandwidth | Product images are served as uploaded (a 1.2 MB JPEG exists), no resize/WebP/dimensions/lazy on key images; 54 backdrop-blur glass surfaces |
| 5 | A-03 | High | Admin workflow | Pending transfers (the only live payment method) have no badge, no default filter, no widget; confirming is not possible from the order page |
| 6 | S-07 | High | Forms | All inputs are 12px (`text-xs`) so iOS/Android zoom on focus; labels not bound to inputs; errors only in a top banner |
| 7 | A-02 | High | Admin perf | Report pages re-run the same queries 2-4x per render (page + widgets), `fulfilmentPerformance` loads all orders 4 times |
| 8 | S-11 | High | Pay | Manual-pay page does not state the payment deadline (stock hold hours) and offers no Copy buttons for the account number |
| 9 | S-15 | High | Reviews | Reviews can be read but never written; no CTA on delivered orders |
| 10 | S-16 | High | Product page | Admin writes `description` with a RichEditor (HTML) but the page prints it escaped, so tags show as literal text (verify with seeded data) |
| 11 | P-01 | High | PWA | Admin "install" shares the storefront manifest, so the installed admin app opens the storefront (`start_url=/`) |
| 12 | S-02 | High | Mobile browse | Filter sidebar renders before results on mobile; header packs logo, search, bell, install, cart on one row at 375px |
| 13 | S-05 | High | Footer/trust | No contact channel (phone, WhatsApp, address) anywhere on the storefront |
| 14 | A-05 | Medium | Admin | Orders table N+1 (`customerName()` hits `user`), no eager load, no bulk actions, no "needs action" tab |
| 15 | B-01 | Medium | Build | `app.js` is an empty file but still shipped and requested; `@source` scans `storage/framework/views` |

Full detail follows. File paths are relative to `C:\xampp\htdocs\cy-market`.

---

## 3. Admin (Filament)

### A-01 Critical - Default 5s polling on stats and chart widgets
- Where: `app/Filament/Widgets/StatsOverviewWidget.php:136` (class), `app/Filament/Widgets/SalesChartWidget.php:74`, and the six widgets in `app/Filament/Pages/Reports/Widgets/*.php`. None override `$pollingInterval`; `vendor/filament/widgets/src/Concerns/CanPoll.php:7` sets `'5s'` and both `ChartWidget` and `StatsOverviewWidget` use it.
- Why: every open admin tab re-runs the widget query set every 5 seconds forever. `StatsOverviewWidget::getStats()` (lines 148-183) triggers `inventoryReport()` (4 queries), `salesSummary()` (5 queries), `customerReport()` (3-4 queries incl. a derived table), plus a count: about 13-15 queries per poll. On the Operations report, `OperationsByStatusChartWidget` polls `operationsReport()` (which itself calls `salesSummary()`), and `FulfilmentPerformanceChartWidget` polls `fulfilmentPerformance()`, which loads every order row in the period into PHP four times (ReportingService.php:416-429). On shared XAMPP/shared hosting this competes with customer traffic; on staff phones it burns data.
- Fix: set `protected static ?string $pollingInterval = null;` on all of them (or '60s' for the dashboard stats only). Add `Cache::remember` (30-60s, keyed by user role + period) inside the `ReportingService` methods the widgets call, and a manual "Refresh" header action. Keep the `databaseNotificationsPolling('30s')` at AdminPanelProvider.php:63; that one is fine (raise to 60s if hosting is tight).

### A-02 High - Report pages recompute the same data repeatedly
- Where: `app/Filament/Pages/Reports/SalesReport.php:529-548`, `OperationsReport.php:472-484`, `InventoryReport.php:398-425`, `CustomersReport.php:332-349` (public methods called straight from Blade); views `resources/views/filament/pages/reports/*.blade.php`; widgets listed above.
- Why: Blade calls `$this->salesSummary()`, `$this->salesByProduct()`, `$this->salesByLocation()` and the chart widgets call `salesByPeriod`, `salesByCategory` independently. Every Livewire re-render of the page (including each period-select change, `->live()` on all three filter fields in `app/Support/ReportPeriod.php:146-164`) re-runs all of it. `OperationsReport` calls `operationsReport()` in the page and again inside `OperationsByStatusChartWidget.php:65`, and `fulfilmentPerformance()` in the page (line 88 of the view) and in `FulfilmentPerformanceChartWidget.php:56`. Custom range `from` and `to` are `->live()` so a change to either field re-queries immediately, even half-filled.
- Fix: (1) memoise per request/period: wrap service methods in `Cache::remember("report.{$name}.{$from}.{$to}", 60, ...)`. (2) Make `from`/`to` `->live(onBlur: true)` or add an explicit "Apply" button. (3) Move chart data and KPIs behind `#[Computed]` properties on the page so the page and widgets share one result. (4) Rewrite `fulfilmentPerformance` with one SQL aggregate (`AVG(TIMESTAMPDIFF(MINUTE, placed_at, paid_at))`; keep a SQLite branch for tests) instead of four `->get()` loads. Request the SQL from backend-dev with exact signatures.

### A-03 High - Pending transfers have no worklist
- Where: `app/Filament/Resources/PaymentResource.php:158-188` (table), `:123` (class), `app/Filament/Resources/OrderResource.php:64-94`.
- Why: pay-by-transfer is the only live payment method (CheckoutController docblock), and every order waits on a human confirming it. Yet: no `getNavigationBadge()` on Payments or Orders, no default filter or tab for `provider=manual, status=pending`, no dashboard widget for it, and the dashboard stat "Orders Awaiting Action" (`StatsOverviewWidget.php:154,166`) counts `PendingPayment + Paid` but its description says "Pending payment or awaiting processing" while `Processing` is excluded. A staff member must open Payments and eyeball the Status column. Customers also wait on this (S-11).
- Fix: (1) `PaymentResource::getNavigationBadge()` = count of pending manual payments, `getNavigationBadgeColor()` warning; same for Orders (Paid, awaiting processing). (2) Add `Tables\Filters\SelectFilter` default `pending` via `->defaultSort`/`->persistFiltersInSession()` or `ListRecords::getTabs()` ("Needs confirmation" / "All"). (3) New dashboard widget "Payments to confirm" (TableWidget, oldest first, with Confirm/Reject actions and proof thumbnail). (4) Fix the stat label to match the query.

### A-04 High - Payment proof and confirm actions are not on the order
- Where: `app/Filament/Resources/OrderResource/Pages/ViewOrder.php:80-92` (Payments section), `PaymentResource.php:172-175` (proof column as a plain "View" text link).
- Why: staff reading an order cannot see the uploaded proof or confirm it; they must leave and find the same order in Payments (no link either way). The Payments table has `order.order_number` as plain text (line 162), not a link. Proof opens as raw file in a new tab, with no preview. Proofs are stored on the public disk (`CheckoutController.php:194`), so the URL is guessable-by-path and cached by the service worker (P-04).
- Fix: add `->url(fn ($record) => OrderResource::getUrl('view', ['record' => $record->order]))` to the order column; add `ImageColumn` thumbnail for proof (or a modal preview action); put "Confirm payment / Reject" header actions on `ViewOrder` reusing `PaymentResource::confirmAction()` logic (needs a `HeaderAction` variant like `OrderResource` already does). Move proofs to a private disk with a signed route (security-reviewer to confirm).

### A-05 Medium - Orders list: N+1, no bulk actions, no operational shortcuts
- Where: `OrderResource.php:64-94`, `ListOrders.php` (empty class).
- Why: `customer` column state calls `$record->customerName()` (`app/Models/Order.php:107`) which touches `$this->user` per row, and nothing eager loads it (no `getEloquentQuery()->with('user')`). 25 rows per page = 25 extra queries; the search callback (lines 69-71) uses `orWhere` + `orWhereHas` with unwrapped `like %term%`. The date filter uses `whereDate` (lines 85-86), which defeats the `(status, created_at)` index. There are no bulk actions (mark as processing/dispatched), no status tabs with counts, no row click-through to View (`recordUrl` default is fine only if View is the sole action), and `Update Status` is hidden from `support-agent` without explanation.
- Fix: override `getEloquentQuery()` with `->with(['user:id,name,phone','deliveryZone:id,name'])`; `ListOrders::getTabs()` with one tab per `OrderStatus` that matters and badge counts; replace `whereDate` with `where('created_at', '>=', Carbon::parse($d)->startOfDay())`; add a bulk "Advance status" action that respects `allowedNextStatuses()`; add `->searchOnBlur()` / `->searchDebounce('500ms')` on the table; expose `delivery_phone` as a tel link for riders.

### A-06 Medium - Dashboard queries per request, no caching
- Where: `app/Filament/Pages/Reports.php:234-293` (`cards()` runs `salesSummary` 5 queries, `inventoryReport` 4, `customerReport` 4+, plus a count on every render of the hub), `StatsOverviewWidget.php:148-183`.
- Why: the Reports hub page is only a menu of 4 cards but runs ~15 aggregate queries; the dashboard stats run a similar set. Both execute on every load, and the hub re-renders on Livewire interactions.
- Fix: one `ReportingService::dashboardSnapshot()` that is cached for 60s and shared by both; or make the hub cards lazy (`wire:init`) with skeleton placeholders.

### A-07 Medium - LowStockProductsWidget loses ordering and N+1s
- Where: `app/Filament/Widgets/LowStockProductsWidget.php:37-39`.
- Why: it runs `lowStockProducts(50)` (ordered by sellable asc) only to take the ids, then queries `Product::whereIn('id', ...)`, which resets ordering to table default and runs two queries; `category.name` is fine via Filament eager loading but `sellable_quantity` is an accessor that cannot be sorted by the table. Table has no empty-state text ("Nothing low on stock"), no default sort, and `->paginated(false)` caps at 50 silently.
- Fix: build the query with `->modifyQueryUsing` using the same `whereRaw` + `orderByRaw('(available_quantity - reserved_quantity) asc')` from `lowStockProducts()`; add `->emptyStateHeading('All stocked up')` and `->emptyStateIcon('heroicon-o-check-circle')`; add a "Restock" bulk action and an "Out of stock" badge colour split (zero vs low).

### A-08 Medium - Report tables silently truncate
- Where: `app/Filament/Pages/Reports/InventoryReport.php:405` (`stockLevels(15)`), `:410` (`lowStockProducts(15)`), `:417`, `:424`; `SalesReport.php:540` (`salesByProduct(..., 15)`); CustomersReport 10 rows.
- Why: "Current Stock Levels" (view `inventory-report.blade.php:150`) shows only 15 of N products, sorted lowest first; headings do not say "top 15", and there is no "View all" link. Staff may assume the list is complete.
- Fix: relabel ("Lowest 15 of {{ $total }}"), link "View all" to `ProductResource` with the low-stock filter pre-applied (`?tableFilters[low_stock][isActive]=true`) and to the CSV export.

### A-09 Medium - Weak dashboards and charts
- Where: `SalesChartWidget.php:88-114`, `Reports/Widgets/*.php`, `ReportingService::topProducts` (ReportingService.php:179, unused anywhere).
- Why: the dashboard is 3 widgets. Missing: recent orders, pending payments (A-03), top products (service method exists, unused), previous-period comparison on KPIs (no "up 12% vs last week" via `->descriptionIcon`/`->chart()` sparkline on `Stat`), currency formatting on chart axes/tooltips (raw numbers, `label: 'Revenue (GHS)'`), date-range selector on the dashboard chart (fixed 14 days; `salesByDay(14)` actually returns 15 days of rows, since it uses `subDays(14)->startOfDay()` while the widget builds 14 labels). Marketing-admin and support-agent roles see an empty dashboard because every widget `canView()` excludes them (`StatsOverviewWidget.php:140-146`, `LowStockProductsWidget.php:26-32`, `SalesChartWidget.php:83-86`), with no welcome or empty state.
- Fix: add `Stat::chart()` sparklines + vs-previous-period deltas, a "Top products" table widget using `topProducts()`, a "Recent orders" widget, `options` for `ticks.callback` formatting with `GHS`, align `salesByDay` window, and a small "Welcome / quick links" widget for roles that otherwise get a blank page.

### A-10 Medium - Product management forms
- Where: `app/Filament/Resources/ProductResource.php:249-327`.
- Why:
  - `name` -> `slug` regenerates with a random suffix on every blur (line 259), so editing a product's name changes its public URL and breaks shared links (also not guarded on Edit).
  - `available_quantity` is editable on Edit despite the helper text saying to use Stock Movements (line 280-281): lets a super admin bypass the audit trail; should be `->disabled()` on edit.
  - `selling_price` has no `minValue(0)`, no `step(0.01)`, no `inputMode('decimal')` (lines 277-278).
  - Images `FileUpload` (lines 308-311) has no `maxSize`, no `imageResizeMode`/`imageResizeTargetWidth`, no `optimize`/WebP conversion: the root cause of S-03 (a 1.2 MB product JPEG already exists in `storage/app/public/products`).
  - Repeater lets staff mark several images `is_primary`, or none.
  - `RichEditor` description output mismatch with storefront (S-16).
  - Season month selects have no validation that start <= end semantics (wrap-around is supported by the model but not explained).
- Fix: slug only auto-generates on create (`->disabled(fn ($operation) => $operation === 'edit')` plus a visible "slug is locked" hint); `->disabled()` on `available_quantity` when editing; add `->maxSize(2048)->imageResizeMode('cover')->imageResizeTargetWidth('1200')->imageResizeTargetHeight('1200')`; enforce a single primary via `->rules()` or an `afterStateUpdated` that clears the others; add helper text for season wrap-around.

### A-11 Medium - Stock movement form and table
- Where: `StockMovementResource.php:52-90`.
- Why: product `Select` preloads `Product::pluck('name','id')` for the whole catalogue as options (line 57) rather than a server-side search; no stock-after preview ("Current: 24 kg, after: 30 kg"); `delta` of 0 is accepted; `quantity` column (line 81) shows unsigned `abs($delta)` for adjustments so the table cannot distinguish stock-in from write-off at a glance (the type badge says only "Adjustment"); no `emptyState`; `product_id` filter loads all products (line 86); Support role has no access, correct, but the "product_id" query default (line 58) reads raw `request()->query()` without validating it exists.
- Fix: `->getSearchResultsUsing(...)` and `->getOptionLabelUsing(...)` on the select; `->notIn([0])` rule; add a computed "Direction" column (green "+" / red "-"), and a live helper showing resulting stock; `->searchable()` on the filter.

### A-12 Low - Tables lack empty/loading/density polish
- Where: all resources, e.g. `CategoryResource.php:160-175`, `BannerResource.php:69-95`, `DeliveryZoneResource.php:225-237`, `UserResource.php:313-332`, `CustomerResource.php:304-324`.
- Why: no `emptyStateHeading/Description/Actions`, so a brand-new shop sees bare "No records found". No `ToggleColumn` for `is_active` (staff must open Edit to disable a product/banner/zone), no `reorderable('sort_order')` for categories/banners, `CategoryResource.parent_id` options include descendants (a category can be set as its own grandchild), `UserResource.roles.name` badge shows raw `super-admin` slugs, `CustomerResource` allows Delete with no warning that order history is kept via `is_active`, passwords have no strength rules or reveal button.
- Fix: add `->emptyStateHeading()` + `CreateAction` where allowed; `ToggleColumn::make('is_active')` on product/banner/zone; `->reorderable('sort_order')` on categories and banners; humanise role names with `formatStateUsing`; `Password::default()` rules and `->revealable()`.

### A-13 Low - Settings page
- Where: `app/Filament/Pages/Settings.php:226-293`, `resources/views/filament/pages/settings.blade.php`.
- Why: section descriptions expose internal doc references ("TOR §3.1", "TOR §11", "TOR §6.6") to non-technical staff (lines 240, 251, 265, 288); only a toast confirms save (no dirty-state warning when navigating away); SMS template placeholders are plain helper text with no validation that required placeholders exist or character counter (320 max, SMS segments); no "send test SMS"; account number field is plain text with no format check; the Save button sits at page bottom after a very long form (11 SMS textareas) with no sticky footer.
- Fix: replace TOR references with plain-language descriptions; use Filament `Form\Actions` sticky footer (`->sticky()` on `Forms\Components\Actions` or `protected static bool $isFooterStickyd`), `->live()` counter helper text on SMS templates, group SMS templates in a collapsed `Section->collapsed()` or `Tabs`; add a "Send test SMS" action.

### A-14 Medium - Admin glass skin: performance and fragility
- Where: `public/css/filament/admin-glass.css:50,75,99-110,143,348` (backdrop-filter), `:29-47` (`!important` + `background-attachment: fixed`).
- Why: `backdrop-filter` is applied to sidebar, topbar, every `.fi-section`, `.fi-ta-ctn`, `.fi-in-ctn`, stat cards, modals and dropdowns. A stats widget now polls every 5s (A-01) and each swap repaints blur layers. The file's own comment (lines 93-98) already records a Chromium compositing bug (blank white content) caused by nested blur. `background-attachment: fixed` forces full-viewport repaint on scroll on mid-range phones. Heavy `!important` on `.fi-*` classes will break on Filament upgrades. No `prefers-reduced-transparency` / `prefers-reduced-motion` handling; `.fi-wi-stats-overview-stat:hover { transform }` also fires on touch.
- Fix: limit blur to the topbar and modals; make section/table cards solid (`var(--fi-glass-bg-strong)` without `backdrop-filter`); drop `background-attachment: fixed`; add `@media (prefers-reduced-transparency: reduce) { * { backdrop-filter: none !important } }` and `(hover: hover)` guard on the hover lift. Move the CSS into a Filament custom theme (`php artisan make:filament-theme`) rather than raw overrides so utilities purge correctly.

### A-15 Low - Accessibility in admin custom views
- Where: `resources/views/filament/pages/reports/*.blade.php` (custom `<table class="cy-table">`), `resources/views/filament/infolists/delivery-location-map.blade.php:345-393`, `partials/filter-bar.blade.php`.
- Why: raw tables have no `<caption>` or `scope="col"` on `<th>`; KPI tiles are `<div>` with no semantic grouping; charts have no text alternative (no data table fallback or `aria-label`); the map block loads Google Maps script inline per infolist render with the key echoed in page source (restrict key by HTTP referrer) and no fallback if the script is blocked offline; "Export CSV" button gives no loading/disabled feedback on large exports (5000-row cap per section).
- Fix: add `<caption class="sr-only">`, `scope="col"`, `aria-label` on chart sections; keep the "key not configured" fallback and add a "script failed to load" fallback with the coordinates link already used (it is good, just show it on error too).

### A-16 Medium - ViewOrder infolist gaps
- Where: `app/Filament/Resources/OrderResource/Pages/ViewOrder.php:36-107`.
- Why: no phone as tap-to-call, no copyable order number, no `discount_total`/`delivery_fee`/`subtotal` breakdown (only `total`), no coupon code, no `on_hold_reason` or `cancelled_reason` shown (so staff cannot see why an order is on hold from the page), status history `note` can be empty with no placeholder, no print/packing-slip action, no items thumbnails or stock-at-time hint. `statusHistories` and `items` relations are not eager-loaded (RepeatableEntry lazy loads; N+1 on `changedBy.name`).
- Fix: add a "Totals" section, reason entries (visible when filled), `->copyable()` / `->url('tel:...')`, a "Print packing slip" header action (Blade view + `window.print()`), and `getEloquentQuery()->with(['items','payments','statusHistories.changedBy','deliveryZone'])`.

---

## 4. Storefront (Blade / Tailwind)

### S-01 Critical - Mobile header hides auth and account actions
- Where: `resources/views/layouts/storefront.blade.php:124` (Account link `hidden sm:inline-flex`), `:130` (Log out `hidden sm:block`), `:135-136` (Log in / Sign up `hidden sm:inline-flex`).
- Why: below 640px, a guest sees no way to log in or register from the header at all (the footer links to Login only, line 239, no Register), and a logged-in customer has no way to reach Account or Log out. The audience is predominantly phone users. Registered-customer value (saved addresses, order history) is unreachable.
- Fix: add a mobile menu button (hamburger or avatar) that opens a simple `<details>` / dialog containing Log in, Sign up, My Orders, Account, Log out, Track order. No Alpine is required: `<details class="relative">` with a styled summary. Add Register to the footer.

### S-02 High - Header crowding and mobile filter placement
- Where: `layouts/storefront.blade.php:56-84` (logo text always visible, search `flex-1 max-w-xl`, action cluster `shrink-0`), `:178-198` (category rail), `:54` (sticky header with two rows plus the top utility bar above, L28-51); `resources/views/storefront/products/index.blade.php:50-126` (sidebar before results).
- Why: at 360-375px the row is logo mark + "CYMarket" wordmark + tagline + search + bell + install + cart; the search input gets roughly 80-100px and its placeholder is clipped. The sticky header is ~110-130px of the viewport on every page on a small phone. On /shop, the full filter panel (categories list `max-h-56`, price, stock, Apply) sits above the first product on mobile, forcing a long scroll before any item is visible.
- Fix: collapse search into an icon-triggered row under the logo on `<sm`, hide tagline and wordmark second line below `sm`, make only the main row sticky (let the category rail scroll away). On `/shop`, wrap the filter card in `<details class="lg:open">` (closed on mobile, with a "Filters (2)" summary button) and put the sort control above the grid as a compact row; keep the sidebar open on `lg`.

### S-03 High - Images and glass effects are heavy for low bandwidth
- Where: `partials/product-card.blade.php:8-11` (img, `loading="lazy"` but no `width/height`, no `srcset`), `storefront/products/show.blade.php:25` (LCP image, no `fetchpriority`/dimensions, no `loading` hint), `storefront/home.blade.php:11-17` (hero video `autoplay` with no `preload="none"` and no `prefers-reduced-motion`/Save-Data gate; hero `<img>` without dimensions), `storefront/cart/index.blade.php:242`, `home.blade.php:119` (category images, no lazy), `app/Models/ProductImage.php:25-27` (`asset('storage/'.$path)`: original file). Only 1 of 6 `<img>` tags in views uses `loading="lazy"`.
- Why: product uploads are not resized (see A-10). The disk already holds a 1.24 MB product JPEG next to 80 KB ones. A grid of 20 cards on /shop could pull well over 5 MB on mobile data. No `width`/`height` also causes layout shift as images load. The visual language uses `.liquid-glass` with `backdrop-filter: blur(28px) saturate(180%)` on ~54 element usages (every product card, plus nav, filters, cards) and `background-attachment: fixed` with six radial gradients on `<body>` (`resources/css/app.css:119-123,198-210`); that is expensive to composite on mid-range Android GPUs and makes scrolling janky.
- Fix: (1) generate responsive derivatives on upload (400/800/1200 WebP via Filament `imageResize*` + a queued conversion, or an image package) and add `ProductImage::thumbUrl()`/`srcset`; (2) add `width`/`height`/`decoding="async"` everywhere and `fetchpriority="high"` + no lazy on the above-the-fold hero/main image; (3) hero video: `preload="none"`, `poster` only, and render only on `(min-width:1024px)` and not on `Save-Data`/`prefers-reduced-motion`; (4) in `app.css` make card glass cheap on touch: `@media (hover:none), (max-width:1023px) { .liquid-glass { backdrop-filter:none; background-color: rgba(255,255,255,.9) } }`, drop `background-attachment: fixed`; (5) cache-bust nothing; leave as is.

### S-04 Medium - Always-running animations
- Where: `partials/product-card.blade.php:29` (`animate-pulse` on every low-stock badge), `products/show.blade.php:43,48,116`, `layouts/storefront.blade.php:33,223` (`animate-ping` forever in footer), `partials/order-status-timeline.blade.php:258` (`animate-ping`), `checkout/sandbox-pay.blade.php:321`. 9 occurrences in total, no `prefers-reduced-motion` guard anywhere.
- Why: continuous animation costs battery, distracts, and violates reduced-motion expectations (WCAG 2.3.3 level AAA / good practice).
- Fix: remove from footer and card badges; wrap remaining ones in `motion-safe:animate-pulse`.

### S-05 High - No way to contact the shop
- Where: `layouts/storefront.blade.php:209-280` (footer), also `checkout/index.blade.php`, `orders/show.blade.php`, `manual-pay.blade.php`.
- Why: a farm-delivery shop taking pay-by-transfer needs a visible phone/WhatsApp number, address and opening hours, especially on payment and on-hold screens ("please contact CY-Market to cancel it" in `OrderController.php:288` gives no contact). Trust for transfer payments depends on this.
- Fix: add `config('cymarket.support_phone')` / `support_whatsapp` and show in footer, header (tel icon), manual-pay, order show (on-hold and pending states), and the cancellation error text. Provide `wa.me` deep link with the order number prefilled.

### S-06 High - Total is unknown until the order exists
- Where: `storefront/checkout/index.blade.php:213-216` ("Calculated on confirmation"), `:147-157` (coupon), `cart/index.blade.php:314-317` ("Calculated at checkout"), controller `CheckoutController.php:36-55`.
- Why: customer is asked to press "Place Order" without seeing the delivery fee, discount, or grand total. The zone select (line 91-99) already lists each fee, but the summary does not update. The coupon field has no Apply button, no validation feedback, and the placeholder suggests a specific code "HARVEST10" (line 154), which may not exist and teaches users a wrong code. Customers on data plans abandon when totals change after commit.
- Fix: compute the fee from the selected zone client-side (data attribute per option) or via a tiny endpoint, show Subtotal + Delivery + Discount + Total live; add an Apply coupon button hitting a JSON validate endpoint (backend-dev) and show success/error inline; remove the hard-coded example code. Keep the server as the authority.

### S-07 High - Form fields are mobile-hostile and not accessible
- Where: every storefront form, e.g. `checkout/index.blade.php:49-52,75-84,123-142`, `auth/login.blade.php:133-149`, `auth/register.blade.php:189-220`, `orders/track.blade.php:93-101`, `products/index.blade.php:99-105`, `account/edit.blade.php` (0 `for=` / `aria-` attributes across the file). 18+ inputs carry `text-xs` (12px).
- Why:
  1. 12px inputs cause iOS Safari to zoom the page on focus and are tiny to read on Android; minimum should be 16px.
  2. `<label>` elements have no `for`, inputs no `id`, so tapping a label does not focus the field and screen readers announce unlabeled fields.
  3. Validation errors only appear as a list in the flash banner at the top of the page (`partials/flash-messages.blade.php:34-48`); after a failed checkout the user lands at the top of a long form, with no per-field message, no `aria-invalid`, no `role="alert"`, no focus move.
  4. Missing `autocomplete` (`name`, `email`, `tel`, `street-address`, `current-password`, `new-password`), `inputmode="tel"`, `enterkeyhint`; auth screens have no show-password toggle.
  5. Tailwind 4 without `@tailwindcss/forms` means `focus:ring-brand-500` on checkboxes (`products/index.blade.php:115`, `login.blade.php:155`) and `focus:ring-0` on qty inputs do nothing; `.liquid-input:focus` swaps box-shadow but `focus:outline-none` removes the native outline for keyboard users (the replacement is only a 1.5px colour change on a translucent field: weak contrast).
- Fix: make a shared Blade component `<x-field name label type autocomplete :error>` that renders `id`/`for`, `text-base sm:text-sm` (>=16px on mobile), `aria-invalid`, `aria-describedby`, and an inline error `<p role="alert">`; add `scroll-to-first-error` via a tiny script (`document.querySelector('[aria-invalid=true]')?.focus()`); set `autocomplete`/`inputmode`; add `:focus-visible` outline (2px brand-600, offset 2px) in `app.css`.

### S-08 Medium - Checkout flow details
- Where: `checkout/index.blade.php:28-237`.
- Why:
  - Submit has no double-click protection (`disabled` + "Placing order..." text on submit); a slow connection invites double orders (server idempotency unknown, request to backend-dev).
  - Sticky summary uses `sticky top-28` at all widths (line 188; same on `cart/index.blade.php:303`): on mobile it sticks under the header while stacked, stealing space. Use `lg:sticky`.
  - Order summary (and Place Order) come after the whole form on mobile; add a compact "Total + Place order" bottom bar.
  - Step indicator (lines 10-25) is decorative `<ol>`, no `aria-current="step"`; step 3 "Confirmation" never exists because after placing the order the user lands on manual-pay (a "Pay" step).
  - Guest: only name and optional email are asked in the contact block; phone is asked below under delivery (fine) but the `old()` handling on `delivery_zone_id` for guests ignores `$defaultAddress`.
  - Logged-in users get only a single default address prefilled; there is no address chooser even though `/account/addresses` supports many.
  - The map picker (S-17) adds a third-party script to the critical path of the form.
- Fix: disable-on-submit script; `lg:sticky`; add mobile sticky bottom bar; add saved-address `<select>` that fills fields; relabel steps (Cart / Delivery / Pay / Done).

### S-09 Medium - Cart interactions
- Where: `storefront/cart/index.blade.php:263-274` (stepper), `:265-273` (inline `onclick` + `onchange="this.form.submit()"`), `:277-279`, controller `CartController.php:158-169`.
- Why: every +/- tap submits a POST and a full page reload (PATCH + redirect). On slow data this is 2-3 seconds per tap, and two quick taps race. The stepper buttons are 28px (`w-7 h-7`) with text "-" / "+" and no `aria-label`; the number input has no label, no `max` (stock) and the page does not show "Only N left" or why an item cannot be increased. The controller passes `$issues` from `validateForCheckout()` (line 163-167) but the view never renders it (grep for `issues` in the view returns nothing), so price/stock changes on a cart item are only discovered at checkout. Category name `$item->product->category->name` (line 252) is not eager loaded (controller loads `items.product.images` only) -> N+1; `load()` then `fresh()` re-queries the whole tree (lines 161,166).
- Fix: render `$issues` as a banner at the top of the cart; add `max` and a "x left" hint per item; make steppers 40px+, add `aria-label="Decrease quantity of {{ name }}"`; progressively enhance with `fetch()` PATCH and in-place totals (keep the form POST as no-JS fallback), debounce 300ms; eager load `items.product.category`; drop the `fresh()`.

### S-10 Medium - Product listing and search
- Where: `products/index.blade.php:3,60-63,99-105,138-157,161-176`, `ProductController.php:56-103`, `Product.php:158-171`.
- Why: results header says "{{ $products->count() }} of {{ $products->total() }} items" in two places (header pill line 46 and toolbar line 134, duplicate). Search: `LIKE %term%` across name, description, sku and a category subquery on every request (no index use, no relevance); no autosuggest, no "did you mean", no empty-search guidance beyond the generic message. Sort `<select onchange=submit>` has a label hidden on mobile (line 142 `hidden sm:inline`), unassociated with the select: the control is unlabeled for screen readers. Hidden fields loop (line 139-141) will emit invalid markup if any query value is an array. Filters have no active-filter chips (user cannot see/remove "Under 20 GHS"), min/max price inputs are `type=number` without labels, `min=0`, `inputmode=decimal`; the currency symbol is hard-coded `₵` (lines 98,103) while elsewhere `config('cymarket.currency_symbol')`. Pagination links render with the default view (verify mobile appearance) and `withQueryString()` is preserved, good. The `title` section for search/category pages is also decorated by the layout suffix (S-14).
- Fix: single count; `aria-label`/visible label for sort; chips for active filters; `type=search` debounce suggest (optional); add FULLTEXT index on `name, short_description` (backend-dev) or at least drop `description` from LIKE; use the config symbol.

### S-11 High - Manual-pay page lacks deadline and copy affordances
- Where: `storefront/checkout/manual-pay.blade.php:35-49` (account block), `:51` (instructions), `:53-68` (upload form); `CheckoutController.php` passes only `$network, $accountName, $accountNumber, $instructions`.
- Why: customers are told "we confirm it on our end" but not when the reservation expires (`manual_payment_reservation_hours`, Settings.php:277-280), so the order may silently lapse. The account number is displayed as text (`text-lg font-mono`); on a phone the customer must retype or long-press-select it into a MoMo/bank app. Reference is truncated to 180px (line 31). Proof upload is optional, but nothing explains that uploading speeds up confirmation, accepts camera photos of several MB with no client-side resize and no progress. `accept=".jpg,.jpeg,.png,.pdf"` without `capture`. After upload the user lands wherever the redirect goes with only a flash (lines 14-18).
- Fix: show "Pay within {{ hours }} hours - your items are held until {{ $expiresAt->format('D d M, H:i') }}" using the reservation expiry; add Copy buttons (`navigator.clipboard.writeText`, plus a visible "Copied" state) for account number, amount and reference; add `inputmode`, `capture="environment"` hint and a client-side canvas downscale (max 1600px) before upload; show a WhatsApp link "Send proof on WhatsApp" as a fallback (S-05); show a clear "Status: awaiting confirmation" tracker with auto-refresh (the order page already polls, link there prominently).

### S-12 Medium - Pending-state messaging
- Where: `storefront/orders/show.blade.php:48-78` (pending box), `:117` and `:121-125` ("Total Paid" label).
- Why: the totals row is labelled "Total Paid" even for unpaid/pending/cancelled orders (line 117 in this file: `Total Paid`), which is wrong and alarming. The pending box says "Stock is reserved" with no time. The polling script (lines 190-217) hits the server every 4s up to 100 times (about 6.7 minutes) for manual payments even though they only change when staff act; it keeps running in a background tab and stops silently after 100 tries. For `momo` live it calls the provider API per poll (`OrderController.php:267-281`).
- Fix: label "Order total" (and "Amount paid" only when a successful payment exists); back off polling (4s -> 15s -> 60s) and use the `visibilitychange` event; show "Last checked 10:42 - Refresh" button after the cap; show the reservation expiry.

### S-13 Medium - Order tracking forms
- Where: `orders/track.blade.php:93-101`, `OrderController.php:322-343`.
- Why: guest lookup requires an exact string match on phone, so "024 123 4567" (the placeholder style) will not match a stored "0241234567" or "+233..."; the only feedback is a generic flash error. `order_number` input is forced `uppercase` via CSS only (submitted value keeps case; MySQL collation might hide it, SQLite not). No rate limiting is visible on `/track-order` or login routes (grep for `throttle` returns nothing in routes/bootstrap), which for a phone+order number lookup enables enumeration.
- Fix: normalise phone to digits (strip spaces, `+233` -> `0`) both when saving and when matching; upper-case order number server-side; helper text showing the exact format from the SMS; add `throttle:10,1`; say "We could not find that order. Check the number in your SMS" with a contact link (S-05).

### S-14 Medium - Titles, meta, SEO and sharing
- Where: `layouts/storefront.blade.php:7-8`, `layouts/guest.blade.php:7`, product pages `products/show.blade.php:3`, cart `cart/index.blade.php:3`.
- Why: layout appends " - Farm-Fresh Produce, Delivered" to every title, and views also append their own brand text, producing titles like "Your Harvest Cart - CY-Market - Farm-Fresh Produce, Delivered" or "Tomatoes - Farm-Fresh Direct - Farm-Fresh Produce, Delivered". The meta description is a single static string for all pages; no Open Graph / Twitter tags, no canonical, no structured data (Product, Offer, AggregateRating). Ghana shoppers share product links on WhatsApp, where rich previews drive clicks. `/robots.txt` exists but there is no sitemap.
- Fix: layout `@hasSection('meta')`/`@stack('meta')`, per-page `@section('title')` containing only the page name with the layout adding one " | CY-Market"; add `og:title/description/image/url` using the product primary image; add JSON-LD for Product on the detail page; generate a sitemap.

### S-15 High - Reviews cannot be written
- Where: `routes/web.php` (no review route), `storefront/orders/show.blade.php` (no CTA), `products/show.blade.php:205-242` (display only); models `Review`, `ReviewImage` exist; controller `ProductController.php:110` loads `reviews.user`.
- Why: the user journey "reviews" is incomplete: ratings can never be created from the storefront, so every product shows "No reviews yet" (`show.blade.php:96`) and the home/cards show "Farm Fresh" placeholder instead of ratings. The reviews list is unbounded (`reviews.user` loads all), has no pagination, and the average uses `average_rating` while the count shown is `reviews->count()` (two sources), possibly including unapproved ones (verify `status` scope: index on `(product_id, status)` suggests moderation exists but the relation is not filtered in the controller).
- Fix: add a "Rate this order" step on delivered orders (`Delivered`/`Completed`) and a form (star radio group with visible labels, optional comment, image upload with size limit); backend to add `ReviewController@store` with verified-purchase check; filter `reviews` to approved + paginate/limit 5 with "Show more"; unify rating source; add Filament ReviewResource for moderation (currently none exists in `app/Filament/Resources`).

### S-16 High (verify) - Rich text description rendered escaped
- Where: `storefront/products/show.blade.php:199-201` (`{{ $product->description }}` with `whitespace-pre-line`), admin `ProductResource.php:271` (`RichEditor`).
- Why: RichEditor stores HTML (`<p>`, `<strong>`, lists). The storefront escapes it, so shoppers will see literal tags. If the product data in use was seeded as plain text it is invisible today, but the first description written through the admin will break.
- Fix: either switch admin to `Textarea` (matching the plain-text storefront) or render sanitised HTML (`{!! Purifier::clean(...) !!}` / `Str::sanitizeHtml`), and use Tailwind typography (`prose`) which is not installed (the `prose prose-sm` classes at line 199 have no effect without `@tailwindcss/typography`). Decide with backend-dev/security-reviewer.

### S-17 Medium - Location picker dependencies and failure modes
- Where: `partials/location-picker.blade.php:29-39` (script push), `:15-26` (no-key fallback), `:98` (deprecated Marker), `:44` (unbound label).
- Why: Google Maps JS (hundreds of KB) is loaded on every checkout, even if the customer picks a zone and never touches the map, on the audience's slowest path. If the script is blocked/offline, `window.__googleMapsReady` never resolves: a blank grey box with no message (only the lack-of-key case is handled). `google.maps.Marker` is deprecated (Google recommends `AdvancedMarkerElement`). The map has no keyboard alternative or text description; the label is not bound to a control. Browser geolocation is requested with `enableHighAccuracy` and a 10s timeout, error text is generic. Lat/lng are optional but the helper says pin "or zone", and the server validates only ranges; a user can submit a pin far outside the pilot zone with no warning.
- Fix: lazy-load the SDK only when the user opens the map (button "Pin my location on a map") or `IntersectionObserver`; add a 8s timeout that shows "Map could not load, you can still type your address"; swap to `AdvancedMarkerElement`; show a gentle warning if pin is >X km from `pilot_zone_lat/lng`; restrict the API key by referrer.

### S-18 Medium - Order timeline accessibility on mobile
- Where: `partials/order-status-timeline.blade.php:20,24-33,39`.
- Why: connectors are `hidden sm:block`, so on phones the steps wrap into a grid of circles with no lines, which reads as unrelated badges; the current step is only a circle with a ping animation; no `aria-current="step"`, no screen-reader text for done/upcoming; upcoming labels use `text-gray-400` (about 2.5:1 on white, fails WCAG AA 4.5:1).
- Fix: render a vertical timeline on `<sm` (left rail), add `aria-current="step"` and `<span class="sr-only">Completed|Current|Upcoming</span>`, use `text-gray-600` for upcoming; show timestamps from `statusHistories` (already loaded in `OrderController::show`) under each reached step.

### S-19 Medium - Global accessibility and contrast
- Where: site-wide. 55 uses of `text-gray-400/300` for informative text, 66 uses of `text-[10px]/[11px]`, 54 glass surfaces over a gradient mesh, `focus:outline-none` on inputs/buttons without a replacement ring (e.g. `product-card.blade.php:90`, `show.blade.php:143`).
- Why: gray-400 (#9ca3af) on white is ~2.5:1; 10-11px text is below comfortable reading size, especially outdoors on phones; translucent surfaces over a gradient make contrast variable; keyboard focus is invisible on several controls.
- Fix: raise body copy to `text-gray-600`/`text-xs` minimum 12px (prefer 14px), use `text-gray-500` only for decorative meta; add a global `:focus-visible { outline: 2px solid var(--color-brand-600); outline-offset: 2px }` in `app.css`; test with axe/Lighthouse.

### S-20 Medium - Navigation and semantics
- Where: `layouts/storefront.blade.php:178-198` (category `<nav>` without label), `products/index.blade.php:9` and `products/show.blade.php:9` (breadcrumb `<nav>` without `aria-label="Breadcrumb"`, last item without `aria-current="page"`), `layouts/storefront.blade.php:90-122` (notification bell), `partials/product-card.blade.php:4,48` (two `<a class="contents">` wrapping blocks).
- Why: `display: contents` on anchors has long-standing accessibility bugs (loses role/focus ring in some browsers/AT); two links to the same product per card produce duplicate tab stops; the bell panel closes on outside click only (no Escape, no focus return); `<main>` exists on the storefront but not on `layouts/guest.blade.php` (used by login, register, track, manual-pay, sandbox-pay); no skip link on guest layout; the icon-only "add to cart" button (`product-card.blade.php:87-90`) has `title` but no `aria-label`, 32px target; the thumbnail buttons on the product page have `alt=""` images inside unlabeled buttons (`show.blade.php:65-69`).
- Fix: one stretched-link per card (`after:absolute after:inset-0` on the title link) and keep Add-to-cart `relative z-10`; add `aria-label`s (`"Add {{ name }} to cart"`); 44px minimum targets; `aria-label` on navs; Escape handler on bell; `<main id="main-content">` and skip link in guest layout; thumbnails get `aria-label="Show image N of M"` and `aria-pressed`.

### S-21 Low - Home page content and efficiency
- Where: `HomeController.php:20-30` (5 queries, 3 `with('images')`), `storefront/home.blade.php:114-126,62-93`, `ShareStorefrontData.php:22-31`.
- Why: static marketing claims ("100% Farm Grown", "Live SMS", "Fast Delivery - Pilot Guaranteed", "Cold-packed") should be confirmed by the business; category tiles all say "Fresh harvest" under the name (line 125) which adds nothing; the three product rails each show 8 items so the home page renders 24 glass cards; the middleware runs the category tree query plus a cart count query on every storefront request including assets-like routes (ShareStorefrontData.php:22-30), uncached.
- Fix: `Cache::remember('nav.categories', 300, ...)` for the category tree; replace the repeated tagline with the product count; merge rails or lazy-load the lower two with `loading="lazy"` + `content-visibility: auto` (`.section-lazy { content-visibility:auto; contain-intrinsic-size: 800px }`).

### S-22 Low - Copy and consistency
- Where: various.
- Why: heavy marketing language ("Harvest Cart", "Locate Harvest Order", "Fresh Cart") coexists with plain language elsewhere; "Total Paid" mislabel (S-12); currency written as `config('cymarket.currency_symbol')` in storefront but `GHS`/`₵` in admin and filters; dates are `d M Y, H:i` consistently (good) but the order page uses an escaped `\a\t`; the footer line "Designed for exceptional farm-to-table commerce" (line 277) is filler; "Instant automated SMS delivery receipt" (line 255) overpromises.
- Fix: one copy pass (frontend-dev with `ux-copy` guidance), centralise money formatting in a `Money::format()` helper/Blade directive used in both panels.

---

## 5. PWA (manifest, service worker, install)

### P-01 High - Admin installs the storefront
- Where: `resources/views/filament/pwa-head.blade.php:6` (links `/manifest.webmanifest`), `public/manifest.webmanifest:4-6` (`start_url: "/?source=pwa"`, `scope: "/"`, `name: CY-Market`), AdminPanelProvider.php:56-59.
- Why: staff who install from `/admin` get an app named "CY-Market" that opens the customer storefront, not the admin panel; the title meta says "CY-Market Admin" but the manifest wins. Orientation is locked to `portrait-primary`, which is wrong for a desktop/tablet back-office install.
- Fix: serve a second manifest `public/admin.webmanifest` (`name: "CY-Market Admin"`, `start_url: "/admin"`, `scope: "/admin/"`, distinct `id`, no orientation lock) and reference it from `pwa-head.blade.php`.

### P-02 Medium - Manifest completeness
- Where: `public/manifest.webmanifest:9-22`.
- Why: only `purpose: "any"` icons (192, 512). No `maskable` icon, so Android crops/shows a white-padded tile; no `id`, `lang`, `categories`, `screenshots` (richer install UI on Android/desktop), no `shortcuts` (Track order, Cart). `public/icons/icon.png` is a 727 KB source image living in the public directory; make sure it is not served/precached and delete it from `public`.
- Fix: add 512 maskable icon, `id: "/"`, `lang: "en-GH"`, `shortcuts`, and move the source art out of `public/`.

### P-03 Medium - Service worker offline behaviour and comment mismatch
- Where: `public/sw.js:52-57,62-76,12-16`.
- Why:
  - Navigation uses a bare network-first `fetch(request)` with no timeout. On patchy 2G/3G the page hangs until the browser's own timeout (often 30-60s) before the offline page ever shows. Add a race with a 6-8s timeout, then fall back to cache or `offline.html`.
  - The header comment says "every /admin request ... is passed straight through, untouched", but navigations to `/admin/*` also go through `respondWith(fetch(...).catch(offline))` (lines 52-57); an admin who loses connection mid-session sees the storefront-branded offline page instead of Filament's own handling. Exclude `/admin` and `/livewire` explicitly.
  - Offline experience is one static page; recently viewed product pages and the catalogue are not available offline, and the cart badge is lost. Cache a small "last N product pages" runtime cache (network-first with timeout) for browse resilience.
  - `CACHE_VERSION` is a manually bumped constant; stale cached `offline.html` after copy changes needs a bump.
  - `skipWaiting()` + `clients.claim()` with no update toast is fine but a new SW can swap assets under a page mid-checkout.
- Fix: implement a timeout race, exclude admin/livewire/api/webhooks, runtime-cache last viewed product pages (cap 30), version `offline.html` URL with a query.

### P-04 Medium - `/storage/` cache has no limit and includes private files
- Where: `public/sw.js:16,62-76`.
- Why: `CACHEABLE_PREFIXES` includes `/storage/`, which includes `/storage/payment-proofs/*` (the customer's payment screenshots, stored publicly, `CheckoutController.php:194`). Those get persisted in the Cache Storage of whichever device viewed them (a shared staff phone), and the cache grows without eviction as shoppers browse, on devices with little storage. `cache.put` is also attempted for range requests (video from banners) which throws on partial (206) responses (`response.ok` is true for 206, `cache.put` rejects it; failure is swallowed only in the outer promise chain, but it is an unhandled rejection).
- Fix: restrict to `/storage/products/`, `/storage/banners/` (images only), `/storage/categories/`; skip if `response.status !== 200`; trim with a max-entries LRU (e.g., 80 images); never cache `payment-proofs`.

### P-05 Low - Install UX
- Where: `layouts/storefront.blade.php:143-147,327-380`.
- Why: the Install button is icon + text hidden on mobile (`hidden sm:inline` span), so on phones it is a bare download icon with only `aria-label`; once dismissed, Android Chrome may not re-fire `beforeinstallprompt`, leaving no way back; iOS standalone with `apple-mobile-web-app-status-bar-style=black-translucent` (line 16) draws content under the status bar and the layout has no `env(safe-area-inset-top)` padding and no `viewport-fit=cover` (verify on an iPhone); modal (`pwa-ios-modal`) has no focus trap/Escape/`role="dialog"`.
- Fix: switch to `default` status bar style or add safe-area padding; add `role="dialog" aria-modal="true"` and Escape handling; show "Install" label on mobile.

---

## 6. Build and assets

### B-01 Medium - Empty JS bundle and over-broad Tailwind sources
- Where: `resources/js/app.js` (single comment line, 0 bytes built: `public/build/assets/app-BvRk9kiK.js`), `vite.config.js:9`, `resources/css/app.css:3-5`.
- Why: `@vite(['resources/css/app.css','resources/js/app.js'])` in both layouts emits a `<script type="module">` for an empty file: one extra request per page for nothing (and the storefront relies on ~150 lines of inline scripts across layout, cart and pickers instead, uncached with every HTML response). `@source '../../storage/framework/views/*.php'` (line 4) makes Tailwind scan compiled Blade views, which include Filament's compiled admin views (221 files now) and stale compiled versions of deleted templates, so the CSS can contain classes nobody uses and the output varies between machines; this line is unnecessary since `../views/**/*.blade.php` is already scanned. Result: 140 KB CSS (about 20 KB gzip), acceptable but avoidable.
- Fix: remove the `app.js` entry until it has real code, or move the inline scripts (bell, PWA install, qty steppers, copy buttons) into it so they cache; delete the storage `@source`; confirm `npm run build` output size afterwards.

### B-02 Low - Fonts
- Where: `vite.config.js:13-19`, `public/build/fonts-manifest.json` (8 preloads: 5 Plus Jakarta weights + 3 Instrument Sans).
- Why: `--font-sans` puts Plus Jakarta Sans first (app.css:8) so Instrument Sans is never used for Latin text, yet three of its weights are preloaded (~50 KB wasted on every first visit); five Jakarta weights (400-800) are preloaded though 400/500/700/800 dominate; the layout still has a manual `preconnect` to fonts.bunny.net (storefront.blade.php:20, guest.blade.php:9) although fonts are self-hosted by the Vite plugin (files are under `/build/assets`), so that connection is unused. `font-black` (900) is used 92 times but the 900 weight is not loaded, so the browser synthesises or falls back to 800.
- Fix: drop `Instrument Sans` from vite fonts and the font stack; keep 400/600/700/800 and map `font-black` to 800 or add 900; remove the Bunny `preconnect`.

### B-03 Low - Admin assets
- Where: `public/css/filament/*`, `public/js/filament/*` committed/published, `AdminPanelProvider.php:47-49`.
- Why: Filament assets are published copies; after a composer update they can drift (`php artisan filament:upgrade` / `filament:assets`), and admin-glass.css is outside any build (no minify, no cache-bust query, versionless URL `asset('css/filament/admin-glass.css')`): browsers/SW may serve stale styles after changes.
- Fix: register through `FilamentAsset::register([Css::make('admin-glass', __DIR__.'/../../resources/css/admin-glass.css')])` (Filament versions the URL) or append `?v={{ filemtime }}`.

---

## 7. Email

### E-01 Medium - Order email is functional but unbranded and thin
- Where: `resources/views/emails/order-event.blade.php:1-21`.
- Why: uses the default Laravel markdown theme (Laravel logo header unless `vendor/mail` is published; `resources/views/vendor` does not exist), the body shows only order number/total/status. Missing: item list, delivery address, payment instructions for pending transfers (the most important email: "Awaiting payment - send GHS X to ..."), support contact, plain-text alternative. Subject and `bodyText` come from the event; no preheader. Currency symbol relies on config (fine). The tracking link is signed (good) but expiry is unknown to the reader.
- Fix: publish the mail theme (`php artisan vendor:publish --tag=laravel-mail`) with brand colours and logo, add items table + delivery block + pay-by-transfer block on `OrderPlaced`/pending events, include contact (S-05), and a text version.

---

## 8. Cross-cutting recommendations (suggested order of work)

1. Quick wins (hours): A-01 (disable polling), S-01 (mobile menu), S-12 label fix, S-14 titles, P-04 cache restrictions, B-01 remove empty bundle and stray `@source`, B-02 fonts, S-19 focus ring.
2. Conversion (1-2 days): S-06 live totals + coupon apply, S-07 field component and 16px inputs, S-11 deadline + copy buttons, S-02 mobile header/filters, S-05 contact info, S-03 image pipeline and cheaper glass on mobile.
3. Staff throughput (1-2 days): A-03 badges/tab/widget, A-04 confirm from order, A-05 eager loading + bulk, A-07 widget fix, A-10 product form protections.
4. Reports (1 day, needs backend-dev for SQL): A-02 caching + single-pass aggregates, A-06 snapshot, A-08/A-09 labelling and richer widgets.
5. Journeys not yet built: S-15 reviews (backend-dev for controller/moderation resource), S-16 description handling decision, P-01 admin manifest.

## 9. Backend needs (handed to backend-dev, per frontend-dev rule 6)

- `ReportingService`: cached `dashboardSnapshot()`; SQL-based `fulfilmentPerformance()` (MySQL `TIMESTAMPDIFF`, SQLite branch for tests); consider an index on `orders(delivery_zone_id)` and `payments(status, provider)`.
- Phone normalisation on order save and tracking lookup; throttle on `/track-order`, `/login`, `/register`.
- Coupon validation JSON endpoint; delivery-fee quote endpoint (zone id -> fee).
- `ReviewController@store` with verified-purchase check, `ReviewResource` for moderation, approved-only relation.
- Image derivatives (thumb/medium WebP) on upload and `ProductImage::srcset()`.
- Private disk plus signed route for payment proofs.
- Reservation expiry exposed to `manualPay` view (`$expiresAt`).
- Config keys: `support_phone`, `support_whatsapp`, business address/hours.

## 10. What is already good (keep)

- Skip link and `<main id>` on the storefront layout; `aria-label` on search submit, cart remove, install and bell buttons; `aria-expanded` on the bell.
- Empty states exist on cart, orders, catalogue search, and report tables; clear confirmation modals with descriptive text on payment confirm/reject.
- Filament authorisation (`canViewAny`, per-role widget `canView`) applied consistently; status machine respected in admin actions.
- Service worker is deliberately conservative about POST/admin/Livewire requests; hashed build assets; `font-display: swap` with size-adjusted fallbacks generated by the Vite font plugin.
- Eager loading on `/shop` and home (`with('images')`), pagination on lists, `(status, created_at)` index on orders.
- Reports period handling and reversed-range guard in `ReportPeriod::range`.
