<?php

namespace Modules\Woocommerce\Http\Controllers;

use App\Business;
use App\Transaction;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Woocommerce\Utils\WoocommerceUtil;
use Illuminate\Support\Facades\Log;
use App\TransactionSellLine;

class WoocommerceWebhookController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $woocommerceUtil;

    protected $moduleUtil;

    protected $transactionUtil;

    protected $productUtil;

    /**
     * Constructor
     *
     * @param  WoocommerceUtil  $woocommerceUtil
     * @return void
     */
    public function __construct(WoocommerceUtil $woocommerceUtil, ModuleUtil $moduleUtil, TransactionUtil $transactionUtil, ProductUtil $productUtil)
    {
        $this->woocommerceUtil = $woocommerceUtil;
        $this->moduleUtil = $moduleUtil;
        $this->transactionUtil = $transactionUtil;
        $this->productUtil = $productUtil;
    }

    /**
     * Function to create sale from woocommerce webhook request.
     *
     * @return Response
     */
    public function orderCreated(Request $request, $business_id)
    {
        try {
            $payload = $request->getContent();
            $business = Business::findOrFail($business_id);
    
            $is_valid_request = $this->isValidWebhookRequest($request, $business->woocommerce_wh_oc_secret);
    
            if (! $is_valid_request) {
                Log::emergency('Woocommerce webhook signature mismatch');
                return;
            }
    
            $user_id = $business->owner->id;
            $woocommerce_api_settings = $this->woocommerceUtil->get_api_settings($business_id);
            $business_data = [
                'id' => $business_id,
                'accounting_method' => $business->accounting_method,
                'location_id' => $woocommerce_api_settings->location_id,
                'business' => $business,
            ];
            $order_data = json_decode($payload);

            DB::beginTransaction();
            $created = $this->woocommerceUtil->createNewSaleFromOrder($business_id, $user_id, $order_data, $business_data);
            $create_error_data = $created !== true ? $created : [];
            $created_data[] = $order_data->number;
    
            if (! empty($created_data)) {
                $this->woocommerceUtil->createSyncLog($business_id, $user_id, 'orders', 'created', $created_data, $create_error_data);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::emergency('File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());
        }
    }


    /**
     * Function to update sale from woocommerce webhook request.
     *
     * @return Response
     */
    public function orderUpdated(Request $request, $business_id)
    {
        try {
            $business = Business::findOrFail($business_id);
            $payload = $request->getContent();

            $is_valid_request = $this->isValidWebhookRequest($request, $business->woocommerce_wh_ou_secret);

            if (! $is_valid_request) {
                \Log::emergency('Woocommerce webhook signature mismatch');
            } else {
                $user_id = $business->owner->id;
                $woocommerce_api_settings = $this->woocommerceUtil->get_api_settings($business_id);
                $business_data = [
                    'id' => $business_id,
                    'accounting_method' => $business->accounting_method,
                    'location_id' => $woocommerce_api_settings->location_id,
                ];

                $order_data = json_decode($payload);

                $sell = Transaction::where('business_id', $business_id)
                                ->where('woocommerce_order_id', $order_data->id)
                                ->with('sell_lines', 'sell_lines.product', 'payment_lines')
                                ->first();

                if (! empty($sell)) {
                    DB::beginTransaction();

                    $updated = $this->woocommerceUtil->updateSaleFromOrder($business_id, $user_id, $order_data, $sell, $business_data);

                    $updated_data[] = $order_data->number;
                    $update_error_data = $updated !== true ? $updated : [];

                    //Create log
                    if (! empty($updated_data)) {
                        $this->woocommerceUtil->createSyncLog($business_id, $user_id, 'orders', 'updated', $updated_data, $update_error_data);
                    }
                    DB::commit();
                }
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
        }
    }

    /**
     * Function to delete sale from woocommerce webhook request.
     *
     * @return Response
     */
    public function orderDeleted(Request $request, $business_id)
    {
        try {
            $business = Business::findOrFail($business_id);
            $payload = $request->getContent();

            $is_valid_request = $this->isValidWebhookRequest($request, $business->woocommerce_wh_od_secret);

            if (! $is_valid_request) {
                \Log::emergency('Woocommerce webhook signature mismatch');
            } else {
                $user_id = $business->owner->id;
                //$woocommerce_api_settings = $this->woocommerceUtil->get_api_settings($business_id);

                $order_data = json_decode($payload);

                $transaction = Transaction::where('business_id', $business_id)
                                ->where('woocommerce_order_id', $order_data->id)
                                ->with('sell_lines')
                                ->first();

                $log_data[] = $transaction->invoice_no;

                DB::beginTransaction();

                if (! empty($transaction)) {
                    $status_before = $transaction->status;
                    $transaction->status = 'draft';
                    $transaction->save();

                    $input['location_id'] = $transaction->location_id;
                    foreach ($transaction->sell_lines as $sell_line) {
                        $input['products']['transaction_sell_lines_id'] = $sell_line->id;
                        $input['products']['product_id'] = $sell_line->product_id;
                        $input['products']['variation_id'] = $sell_line->variation_id;
                        $input['products']['quantity'] = $sell_line->quantity;
                    }

                    //Update product stock
                    $this->productUtil->adjustProductStockForInvoice($status_before, $transaction, $input);

                    $business = ['id' => $business_id,
                        'accounting_method' => $business->accounting_method,
                        'location_id' => $transaction->location_id,
                    ];
                    $this->transactionUtil->adjustMappingPurchaseSell($status_before, $transaction, $business);
                }

                //Create log
                if (! empty($log_data)) {
                    $this->woocommerceUtil->createSyncLog($business_id, $user_id, 'orders', 'deleted', $log_data);
                }

                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
        }
    }

    /**
     * Function to restore sale from woocommerce webhook request.
     *
     * @return Response
     */
    public function orderRestored(Request $request, $business_id)
    {
        try {
            $business = Business::findOrFail($business_id);
            $payload = $request->getContent();

            $is_valid_request = $this->isValidWebhookRequest($request, $business->woocommerce_wh_or_secret);

            if (! $is_valid_request) {
                \Log::emergency('Woocommerce webhook signature mismatch');
            } else {
                $user_id = $business->owner->id;
                $woocommerce_api_settings = $this->woocommerceUtil->get_api_settings($business_id);
                $business_data = [
                    'id' => $business_id,
                    'accounting_method' => $business->accounting_method,
                    'location_id' => $woocommerce_api_settings->location_id,
                    'business' => $business,
                ];

                $order_data = json_decode($payload);
                $sell = Transaction::where('business_id', $business_id)
                                ->where('woocommerce_order_id', $order_data->id)
                                ->with('sell_lines', 'sell_lines.product', 'payment_lines')
                                ->first();

                DB::beginTransaction();
                //If sell not deleted restore from draft else create new sale
                if (! empty($sell)) {
                    $updated = $this->woocommerceUtil->updateSaleFromOrder($business_id, $user_id, $order_data, $sell, $business_data);

                    $updated_data[] = $order_data->number;
                    $update_error_data = $updated !== true ? $updated : [];

                    //Create log
                    if (! empty($updated_data)) {
                        $this->woocommerceUtil->createSyncLog($business_id, $user_id, 'orders', 'restored', $updated_data, $update_error_data);
                    }
                } else {
                    $created = $this->woocommerceUtil->createNewSaleFromOrder($business_id, $user_id, $order_data, $business_data);

                    $create_error_data = $created !== true ? $created : [];
                    $created_data[] = $order_data->number;

                    //Create log
                    if (! empty($created_data)) {
                        $this->woocommerceUtil->createSyncLog($business_id, $user_id, 'orders', 'created', $created_data, $create_error_data);
                    }
                }

                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
        }
    }

    /**
     * API endpoint to update custom meta data (staff_note) for a specific order
     * Can be called from WooCommerce or external systems
     *
     * @param  Request  $request
     * @param  int  $business_id
     * @return Response
     */
    public function updateOrderCustomMeta(Request $request, $business_id)
    {
        try {
            // Validate API key/secret
            $api_key = $request->header('X-API-Key') ?? $request->input('api_key');
            $business = Business::findOrFail($business_id);
            
            // Use the order update webhook secret for API authentication
            if (empty($api_key) || $api_key !== $business->woocommerce_wh_ou_secret) {
                return response()->json([
                    'success' => 0,
                    'msg' => 'Unauthorized: Invalid API key'
                ], 401);
            }

            $woocommerce_order_id = $request->input('woocommerce_order_id');
            
            if (empty($woocommerce_order_id)) {
                return response()->json([
                    'success' => 0,
                    'msg' => 'WooCommerce Order ID is required'
                ], 400);
            }

            // Find transaction in POS
            $transaction = Transaction::where('business_id', $business_id)
                ->where('woocommerce_order_id', $woocommerce_order_id)
                ->first();

            if (empty($transaction)) {
                return response()->json([
                    'success' => 0,
                    'msg' => 'Order not found in POS. WooCommerce Order ID: ' . $woocommerce_order_id
                ], 404);
            }

            // Fetch order from WooCommerce
            $woocommerce = $this->woocommerceUtil->woo_client($business_id);
            $order = $woocommerce->get('orders/' . $woocommerce_order_id);

            if (empty($order)) {
                return response()->json([
                    'success' => 0,
                    'msg' => 'Order not found in WooCommerce. Order ID: ' . $woocommerce_order_id
                ], 404);
            }

            // Extract custom meta from line items
            $staff_note = '';
            foreach ($order->line_items as $product_line) {
                $game_title = null;
                $account = null;
                $password = null;
                $type = null;

                // Extract meta_data values
                if (!empty($product_line->meta_data)) {
                    foreach ($product_line->meta_data as $meta) {
                        if ($meta->key === 'game_title') {
                            $game_title = $meta->value;
                        } elseif ($meta->key === '_account') {
                            $account = $meta->value;
                        } elseif ($meta->key === '_password') {
                            $password = $meta->value;
                        } elseif ($meta->key === 'type') {
                            $type = $meta->value;
                        }
                    }
                }

                $staff_note .= "\nGame Title: " . ($game_title ?? 'N/A') . 
                              "\nType: " . ($type ?? 'N/A') . 
                              "\nAccount: " . ($account ?? 'N/A') . 
                              "\nPassword: " . ($password ?? 'N/A') . 
                              "<br>----------------------<br>";
            }

            // Update transaction staff_note
            $transaction->staff_note = $staff_note;
            $transaction->save();

            return response()->json([
                'success' => 1,
                'msg' => 'Custom meta data updated successfully for Order #' . $order->number,
                'invoice_no' => $transaction->invoice_no,
                'woocommerce_order_id' => $woocommerce_order_id,
                'staff_note' => $staff_note
            ], 200);

        } catch (\Exception $e) {
            Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return response()->json([
                'success' => 0,
                'msg' => 'Failed to update: ' . $e->getMessage()
            ], 500);
        }
    }

    private function isValidWebhookRequest($request, $secret)
    {
        $signature = $request->header('x-wc-webhook-signature');

        $payload = $request->getContent();
        $calculated_hmac = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        if ($signature != $calculated_hmac) {
            return false;
        } else {
            return true;
        }
    }
}
