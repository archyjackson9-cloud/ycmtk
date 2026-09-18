<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Server-to-server Hubtel payment callback (TOR §5.2, §10). Idempotent -
 * see HubtelPaymentService::handleCallback(). Always returns 200 for a
 * payload we understood (even if the transaction was already processed) so
 * Hubtel does not retry unnecessarily; genuine failures return a non-2xx
 * so Hubtel's own retry/reconciliation kicks in (TOR §11 "System downtime
 * during payment -> Hubtel webhook + reconciliation job matches payments
 * to orders when the system recovers").
 */
class HubtelWebhookController extends Controller
{
    public function handle(Request $request, PaymentGatewayInterface $gateway): JsonResponse
    {
        Log::channel('hubtel')->info('Hubtel webhook received', $request->all());

        try {
            $payment = $gateway->handleCallback($request->all());

            return response()->json(['status' => 'ok', 'reference' => $payment->reference]);
        } catch (NotFoundHttpException|ModelNotFoundException $e) {
            Log::channel('hubtel')->warning('Hubtel webhook for unknown reference', ['payload' => $request->all()]);

            return response()->json(['status' => 'unknown_reference'], 422);
        } catch (\Throwable $e) {
            Log::channel('hubtel')->error('Hubtel webhook processing failed', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error'], 500);
        }
    }
}
