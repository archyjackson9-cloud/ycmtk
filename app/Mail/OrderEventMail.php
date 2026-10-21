<?php

namespace App\Mail;

use App\Enums\OrderEmailType;
use App\Models\Order;
use App\Services\Notifications\EmailNotificationService;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Branded order email (order placed / every status change / payment not
 * completed) with an HTML and a plain-text part. Deliberately NOT
 * ShouldQueue itself: it is rendered and sent inside the SendOrderEmail
 * job, which owns queueing, retries and NotificationLog bookkeeping.
 */
class OrderEventMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public OrderEmailType $type) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('app.name').' - '.$this->type->label().' (Order '.$this->order->order_number.')',
        );
    }

    public function content(): Content
    {
        $settings = app(SettingsService::class);
        $payment = $this->order->latestPayment();

        // Pay-by-transfer instructions only matter while the order is still
        // waiting for the customer's transfer.
        $showPaymentInstructions = $this->type === OrderEmailType::Placed
            && $payment?->provider === 'manual'
            && $settings->manualPaymentConfigured();

        $data = [
            'order' => $this->order,
            'items' => $this->order->items,
            'type' => $this->type,
            'copy' => app(EmailNotificationService::class)->content($this->order, $this->type),
            'currency' => config('cymarket.currency_symbol'),
            'scheduledLabel' => $this->order->scheduledDeliveryLabel(),
            'zoneName' => $this->order->deliveryZone?->name,
            'showPayment' => $showPaymentInstructions,
            'payment' => $showPaymentInstructions ? [
                'network' => $settings->manualPaymentNetwork(),
                'account_name' => $settings->manualPaymentAccountName(),
                'account_number' => $settings->manualPaymentAccountNumber(),
                'instructions' => $settings->manualPaymentInstructions(),
            ] : null,
            // Signed so a guest can open their tracking page straight from
            // the email on any device, without the session that placed the
            // order (see OrderController::authorizeOrderAccess()).
            'trackingUrl' => URL::signedRoute('orders.show', ['order' => $this->order->id]),
        ];

        return new Content(
            view: 'emails.order-event',
            text: 'emails.order-event-text',
            with: $data,
        );
    }
}
