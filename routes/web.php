<?php

use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\AddressController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\OrderController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Webhooks\HubtelWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Storefront (TOR §8.2 Key Screens: Homepage, Category/search, Product
| detail, Cart, Checkout, Order tracking/history, Account)
|--------------------------------------------------------------------------
*/
Route::middleware('storefront')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/shop', [ProductController::class, 'index'])->name('products.index');
    Route::get('/products/{product:slug}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');

    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/{item}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/{item}', [CartController::class, 'destroy'])->name('cart.destroy');

    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('/checkout/return/{reference}', [CheckoutController::class, 'returnFromGateway'])->name('checkout.return');
    Route::get('/checkout/sandbox-pay/{reference}', [CheckoutController::class, 'sandboxPay'])->name('checkout.sandbox-pay');
    Route::post('/checkout/sandbox-pay/{reference}', [CheckoutController::class, 'sandboxConfirm'])->name('checkout.sandbox-confirm');

    Route::get('/track-order', [OrderController::class, 'trackForm'])->name('orders.track-form');
    Route::post('/track-order', [OrderController::class, 'track'])->name('orders.track');

    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('/orders/{order}/resolve-hold', [OrderController::class, 'resolveHold'])->name('orders.resolve-hold');

    Route::middleware('auth')->group(function () {
        Route::get('/my-orders', [OrderController::class, 'index'])->name('orders.index');

        Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
        Route::patch('/account', [AccountController::class, 'update'])->name('account.update');

        Route::post('/account/addresses', [AddressController::class, 'store'])->name('addresses.store');
        Route::patch('/account/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
        Route::delete('/account/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
    });

    /*
    |----------------------------------------------------------------------
    | Auth (hand-rolled Breeze-style: login, register, password reset -
    | Blade + Tailwind, no email verification gate for the SMS-first pilot)
    |----------------------------------------------------------------------
    */
    Route::middleware('guest')->group(function () {
        Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
        Route::post('/register', [RegisteredUserController::class, 'store']);

        Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AuthenticatedSessionController::class, 'store']);

        Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

        Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
    });

    Route::middleware('auth')->post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Webhooks - server-to-server, no storefront chrome/session data needed
| (TOR §5.2 Payment Service webhook handling). CSRF is excepted for this
| path in bootstrap/app.php.
|--------------------------------------------------------------------------
*/
Route::post('/webhooks/hubtel', [HubtelWebhookController::class, 'handle'])->name('webhooks.hubtel');

/*
|--------------------------------------------------------------------------
| Admin report exports (TOR §6.11 "export capability"). Lives outside the
| Filament panel's own routing since it streams a file rather than
| rendering a Livewire page; RBAC is enforced inside the controller.
|--------------------------------------------------------------------------
*/
Route::middleware('auth')
    ->get('/admin/reports/export/{report}', ReportExportController::class)
    ->name('admin.reports.export');
