<?php

namespace App\Mail;

use App\Enums\OrderEventType;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Order-tracking-update email - the email counterpart to every SMS trigger
 * sent through SmsNotificationService (TOR §6.6), dispatched alongside it
 * by EmailNotificationService whenever the order has an email address on
 * file. Sent synchronously (not ShouldQueue) so the NotificationLog status
 * written by EmailNotificationService reflects what actually happened,
 * the same guarantee the SMS gateway gives.
 */
class OrderEventMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public OrderEventType $event, public string $bodyText) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'CY-Market - '.$this->event->label().' (Order '.$this->order->order_number.')',
        );
    }

    public function content(): Content
    {
        // Signed so a guest can open their tracking page straight from the
        // email on any device, without needing the session that originally
        // placed the order (see OrderController::authorizeOrderAccess()).
        $trackingUrl = URL::signedRoute('orders.show', ['order' => $this->order->id]);

        return new Content(
            view: 'emails.order-event',
            with: [
                'order' => $this->order,
                'event' => $this->event,
                'bodyText' => $this->bodyText,
                'trackingUrl' => $trackingUrl,
            ],
        );
    }
}
