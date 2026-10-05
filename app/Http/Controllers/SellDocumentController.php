<?php

namespace App\Http\Controllers;

use App\Services\SellDocumentService;
use App\Transaction;
use Illuminate\Http\JsonResponse;

/**
 * Removes attached documents from a sell / sales order (edit form "Attach Documents" list).
 */
class SellDocumentController extends Controller
{
    public function __construct(private SellDocumentService $sellDocuments)
    {
    }

    public function destroy(int $transactionId, int $mediaId): JsonResponse
    {
        $transaction = $this->authorizedTransaction($transactionId);

        try {
            $this->sellDocuments->deleteDocument((int) $transaction->business_id, $transaction, $mediaId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'msg' => __('messages.something_went_wrong')], 404);
        } catch (\Throwable $e) {
            \Log::error('Sell document delete failed', ['transaction_id' => $transactionId, 'media_id' => $mediaId, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'msg' => __('messages.something_went_wrong')], 500);
        }

        return response()->json(['success' => true, 'msg' => __('lang_v1.file_deleted_successfully')]);
    }

    public function destroyLegacy(int $transactionId): JsonResponse
    {
        $transaction = $this->authorizedTransaction($transactionId);

        try {
            $this->sellDocuments->deleteLegacyDocument($transaction);
        } catch (\Throwable $e) {
            \Log::error('Sell legacy document delete failed', ['transaction_id' => $transactionId, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'msg' => __('messages.something_went_wrong')], 500);
        }

        return response()->json(['success' => true, 'msg' => __('lang_v1.file_deleted_successfully')]);
    }

    private function authorizedTransaction(int $transactionId): Transaction
    {
        $user = auth()->user();
        if (! $user->can('sell.update') && ! $user->can('direct_sell.update') && ! $user->can('so.update')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = (int) request()->session()->get('user.business_id');

        return Transaction::where('business_id', $businessId)
            ->whereIn('type', ['sell', 'sales_order'])
            ->findOrFail($transactionId);
    }
}
