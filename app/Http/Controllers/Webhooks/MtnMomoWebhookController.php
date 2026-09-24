<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server MTN MoMo payment callback (TOR §5.2, §10). Idempotent -
 * see MtnMomoPaymentService::handleCallback(), which also re-verifies the
 * status with MTN in live mode because callbacks are unsigned. Returns 200
 * for anything we understood (even if already processed) so MTN does not
 * retry; genuine failures return non-2xx so it does.
 */
class MtnMomoWebhookController extends Controller
{
    public function handle(Request $request, PaymentGatewayInterface $gateway): JsonResponse
    {
        Log::channel('momo')->info('MTN MoMo callback received', $request->all());

        try {
            $payment = $gateway->handleCallback($request->all());

            return response()->json(['status' => 'ok', 'reference' => $payment->reference]);
        } catch (ModelNotFoundException) {
            Log::channel('momo')->warning('MTN MoMo callback for unknown reference', ['payload' => $request->all()]);

            return response()->json(['status' => 'unknown_reference'], 422);
        } catch (\Throwable $e) {
            Log::channel('momo')->error('MTN MoMo callback processing failed', ['error' => $e->getMessage()]);

            return response()->json(['status' => 'error'], 500);
        }
    }
}
