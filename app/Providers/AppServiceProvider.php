<?php

namespace App\Providers;

use App\Services\Notifications\SmsGatewayInterface;
use App\Services\Notifications\SmsNotificationService;
use App\Services\Payments\HubtelPaymentService;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bound as interfaces so the payment/SMS gateways can be swapped
        // (e.g. an additional Mobile Money network per TOR §6.5 Expansion)
        // without touching CheckoutService/OrderService (TOR §5.2 "Payment
        // Service abstraction ... designed from Phase 1 to support
        // multiple gateways without touching the Order Management
        // Service").
        $this->app->bind(PaymentGatewayInterface::class, HubtelPaymentService::class);
        $this->app->bind(SmsGatewayInterface::class, SmsNotificationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
