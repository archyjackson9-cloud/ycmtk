<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The storefront bell (TOR-adjacent: in-app order-tracking updates for
 * registered customers - see OrderStatusUpdated, ShareStorefrontData).
 * Guests never reach here - there's no account to show a bell in.
 */
class NotificationController extends Controller
{
    /**
     * Clicking a notification both marks it read and takes the customer
     * straight to the order it's about - one request instead of a JS click
     * handler, since the storefront avoids a JS framework.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $record = $request->user()->notifications()->findOrFail($notification);
        $record->markAsRead();

        return redirect($record->data['url'] ?? route('orders.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
