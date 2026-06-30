<?php

namespace App\Http\Controllers\Api\Storefront;

use App\Services\Storefront\StorefrontSettingService;
use App\Transaction;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Payment gateway webhooks for storefront orders.
 */
class PaymentWebhookController extends StorefrontController
{
    public function __construct(
        private StorefrontSettingService $settings,
        private TransactionUtil $transactionUtil
    ) {
    }

    public function handle(Request $request, string $provider)
    {
        $businessId = $this->businessId($request);
        $config = $this->settings->get($businessId);

        if (($config['gateway']['provider'] ?? '') !== $provider) {
            return $this->jsonError('Unknown payment provider.', 400);
        }

        $payload = $request->all();
        $orderId = $payload['storefront_order_id'] ?? $payload['merchant_order_id'] ?? $payload['order_id'] ?? null;
        $status = $payload['status'] ?? $payload['payment_status'] ?? null;

        if (empty($orderId)) {
            Log::warning('Storefront payment webhook missing order id', ['provider' => $provider, 'payload' => $payload]);

            return $this->jsonError('Missing order reference.', 422);
        }

        $transaction = Transaction::where('business_id', $businessId)
            ->where('storefront_order_id', $orderId)
            ->first();

        if (empty($transaction)) {
            return $this->jsonError('Order not found.', 404);
        }

        if (in_array(strtolower((string) $status), ['paid', 'success', 'completed'], true)) {
            $this->transactionUtil->createOrUpdatePaymentLines($transaction, [[
                'amount' => (float) $transaction->final_total,
                'method' => 'card',
                'payment_line_status' => 'completed',
            ]], $businessId, 1, false);

            $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);
        }

        return $this->jsonSuccess(['received' => true, 'order_id' => $transaction->id]);
    }
}
