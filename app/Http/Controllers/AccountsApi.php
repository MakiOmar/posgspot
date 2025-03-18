<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Business;
use App\Transaction;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use DB;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use App\TransactionSellLine;

use App\Category;
use App\Contact;
use App\Exceptions\PurchaseSellMismatch;
use App\Product;
use App\TaxRate;
use App\Utils\ContactUtil;
use App\Utils\Util;
use App\VariationLocationDetails;
use App\VariationTemplate;
use Automattic\WooCommerce\Client;
use Modules\Woocommerce\Entities\WoocommerceSyncLog;
use Modules\Woocommerce\Exceptions\WooCommerceError;

class AccountsApi extends Controller
{
    /**
     * Constructor
     *
     * @param  WoocommerceUtil  $woocommerceUtil
     * @return void
     */
    public function __construct( TransactionUtil $transactionUtil )
    {
        $this->transactionUtil = $transactionUtil;

    }
    public function orderCreated( Request $request, $business_id ) {
        try {
            $payload = $request->getContent();
            $business = Business::findOrFail($business_id);
            $user_id = $business->owner->id;
            $business_data = [
                'id' => $business_id,
                'accounting_method' => $business->accounting_method,
                'location_id' => 1,
                'business' => $business,
            ];
            $order_data = json_decode($payload);
            DB::beginTransaction();
            $created = $this->createNewSaleFromOrder($business_id, $user_id, $order_data, $business_data);
            $create_error_data = $created !== true ? $created : [];
            DB::commit();
            return response()->json([ 'message' => 'Order has been created successfully' ], 200);
        } catch ( \Exception $e ) {
            return response()->json([
                'message' => 'Error occured!',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * Creates new sales in POSfrom woocommerce order list
     *
     * @param  id  $business_id
     * @param  id  $user_id
     * @param  obj  $order
     * @param  array  $business_data
     */
    public function createNewSaleFromOrder($business_id, $user_id, $order, $business_data)
    {
        $input = $this->formatOrderToSale($business_id, $user_id, $order);
        
        if (! empty($input['has_error'])) {
            return $input['has_error'];
        }

        $invoice_total = [
            'total_before_tax' => $order->total,
            'tax' => 0,
        ];

        DB::beginTransaction();

        $transaction = $this->transactionUtil->createSellTransaction($business_id, $input, $invoice_total, $user_id, false);
        $transaction->save();

        //Create sell lines
        $this->transactionUtil->createOrUpdateSellLines($transaction, $input['products'], $input['location_id'], false, null, ['account_line_items_id' => 'line_item_id'], false);

        $this->transactionUtil->createOrUpdatePaymentLines($transaction, $input['payment'], $business_id, $user_id, false);

        if ($input['status'] == 'final') {
            //update product stock
            foreach ($input['products'] as $product) {
                if ($product['enable_stock']) {
                    $this->productUtil->decreaseProductQuantity(
                        $product['product_id'],
                        $product['variation_id'],
                        $input['location_id'],
                        $product['quantity']
                    );
                }
            }

            //Update payment status
            $transaction->payment_status = 'paid';
            $transaction->save();

            try {
                $this->transactionUtil->mapPurchaseSell($business_data, $transaction->sell_lines, 'purchase');
            } catch (PurchaseSellMismatch $e) {
                DB::rollBack();

                return [
                    'error_type' => 'order_insuficient_product_qty',
                    'order_number' => $order->number,
                    'msg' => $e->getMessage(),
                ];
            }
        }

        DB::commit();

        return true;
    }
    
    /**
     * Formats Woocommerce order response to pos sale request
     *
     * @param  id  $business_id
     * @param  id  $user_id
     * @param  obj  $order
     * @param  obj  $sell = null
     */
    public function formatOrderToSale($business_id, $user_id, $order, $sell = null)
    {
        /**
         * Notes:
         * location_id needs to be dynamic
         * product_line_product_id needs to be dynamic
         */
    
        //Create sell line data
        $product_lines = [];
        
        //For updating sell lines
        $sell_lines = [];
        if (! empty($sell)) {
            $sell_lines = $sell->sell_lines;
        }
    
        foreach ($order->line_items as $product_line) {
            $product_line_product_id = 39;
            $product = Product::where('business_id', $business_id)
                            ->where('id', $product_line_product_id)
                            ->with(['variations'])
                            ->first();
    
            $unit_price = $product_line->total / $product_line->quantity;
            $line_tax = ! empty($product_line->total_tax) ? $product_line->total_tax : 0;
            $unit_line_tax = $line_tax / $product_line->quantity;
            $unit_price_inc_tax = $unit_price + $unit_line_tax;
            if (! empty($product)) {
                $variation = $product->variations->first();
    
                if (empty($variation)) {
                    return ['has_error' => [
                        'error_type' => 'order_product_not_found',
                        'order_number' => $order->number,
                        'product' => $product_line->name.' SKU:'.$product_line->sku,
                    ],
                    ];
                    exit;
                }
    
                //Check if line tax exists append to sale line data
                $tax_id = null;
    
                $product_data = [
                    'product_id' => $product->id,
                    'unit_price' => $unit_price,
                    'unit_price_inc_tax' => $unit_price_inc_tax,
                    'variation_id' => $variation->id,
                    'quantity' => $product_line->quantity,
                    'enable_stock' => $product->enable_stock,
                    'item_tax' => $line_tax,
                    'tax_id' => $tax_id,
                    'line_item_id' => $product_line->id,
                ];
    
                $product_lines[] = $product_data;
            } else {
                return ['has_error' => [
                    'error_type' => 'order_product_not_found',
                    'order_number' => $order->number,
                    'product' => $product_line->name.' SKU:'.$product_line->sku,
                ],
                ];
                exit;
            }
        }

        $f_name = ! empty($order->billing->first_name) ? $order->billing->first_name : '';
        $l_name = ! empty($order->billing->last_name) ? $order->billing->last_name : '';
        $customer_details = [
            'first_name' => $f_name,
            'last_name' => $l_name,
            'email' => ! empty($order->billing->email) ? $order->billing->email : null,
            'name' => $f_name.' '.$l_name,
            'mobile' => $order->billing->phone,
            'address_line_1' => ! empty($order->billing->address_1) ? $order->billing->address_1 : null,
            'address_line_2' => ! empty($order->billing->address_2) ? $order->billing->address_2 : null,
            'city' => ! empty($order->billing->city) ? $order->billing->city : null,
            'state' => ! empty($order->billing->state) ? $order->billing->state : null,
            'country' => ! empty($order->billing->country) ? $order->billing->country : null,
            'zip_code' => ! empty($order->billing->postcode) ? $order->billing->postcode : null,
        ];
        
        if (! empty($customer_details['mobile'])) {
            $customer = Contact::where('business_id', $business_id)
                            ->where('mobile', $customer_details['mobile'])
                            ->OnlyCustomers()
                            ->first();
        }
        //If customer not found create new
        if (empty($customer)) {
            $ref_count = $this->transactionUtil->setAndGetReferenceCount('contacts', $business_id);
            $contact_id = $this->transactionUtil->generateReferenceNumber('contacts', $ref_count, $business_id);
    
            $customer_data = [
                'business_id' => $business_id,
                'type' => 'customer',
                'first_name' => $customer_details['first_name'],
                'last_name' => $customer_details['last_name'],
                'name' => $customer_details['name'],
                'email' => $customer_details['email'],
                'contact_id' => $contact_id,
                'mobile' => $customer_details['mobile'],
                'city' => $customer_details['city'],
                'state' => $customer_details['state'],
                'country' => $customer_details['country'],
                'created_by' => $user_id,
                'address_line_1' => $customer_details['address_line_1'],
                'address_line_2' => $customer_details['address_line_2'],
                'zip_code' => $customer_details['zip_code'],
            ];
            //if name is blank make email address as name
            if (empty(trim($customer_data['name']))) {
                $customer_data['first_name'] = $customer_details['email'];
                $customer_data['name'] = $customer_details['email'];
            }
            $customer = Contact::create($customer_data);
        }
    
        $sell_status = 'final';
        $shipping_status = 'ordered';
        $shipping_address = [];
        if (! empty($order->shipping->first_name)) {
            $shipping_address[] = $order->shipping->first_name.' '.$order->shipping->last_name;
        }
        if (! empty($order->shipping->company)) {
            $shipping_address[] = $order->shipping->company;
        }
        if (! empty($order->shipping->address_1)) {
            $shipping_address[] = $order->shipping->address_1;
        }
        if (! empty($order->shipping->address_2)) {
            $shipping_address[] = $order->shipping->address_2;
        }
        if (! empty($order->shipping->city)) {
            $shipping_address[] = $order->shipping->city;
        }
        if (! empty($order->shipping->state)) {
            $shipping_address[] = $order->shipping->state;
        }
        if (! empty($order->shipping->country)) {
            $shipping_address[] = $order->shipping->country;
        }
        if (! empty($order->shipping->postcode)) {
            $shipping_address[] = $order->shipping->postcode;
        }
        $addresses['shipping_address'] = [
            'shipping_name' => $order->shipping->first_name.' '.$order->shipping->last_name,
            'company' => $order->shipping->company,
            'shipping_address_line_1' => $order->shipping->address_1,
            'shipping_address_line_2' => $order->shipping->address_2,
            'shipping_city' => $order->shipping->city,
            'shipping_state' => $order->shipping->state,
            'shipping_country' => $order->shipping->country,
            'shipping_zip_code' => $order->shipping->postcode,
        ];
        $addresses['billing_address'] = [
            'billing_name' => $order->billing->first_name.' '.$order->billing->last_name,
            'company' => $order->billing->company,
            'billing_address_line_1' => $order->billing->address_1,
            'billing_address_line_2' => $order->billing->address_2,
            'billing_city' => $order->billing->city,
            'billing_state' => $order->billing->state,
            'billing_country' => $order->billing->country,
            'billing_zip_code' => $order->billing->postcode,
        ];
    
        $shipping_lines_array = [];
        if (! empty($order->shipping_lines)) {
            foreach ($order->shipping_lines as $shipping_lines) {
                $shipping_lines_array[] = $shipping_lines->method_title;
            }
        }
    
        $new_sell_data = [
            'business_id' => $business_id,
            'location_id' => 1,
            'contact_id' => $customer->id,
            'discount_type' => 'fixed',
            'discount_amount' => $order->discount_total,
            'shipping_charges' => $order->shipping_total,
            'final_total' => $order->total,
            'created_by' => $user_id,
            'status' => $sell_status == 'quotation' ? 'draft' : $sell_status,
            'is_quotation' => $sell_status == 'quotation' ? 1 : 0,
            'sub_status' => $sell_status == 'quotation' ? 'quotation' : null,
            'payment_status' => 'paid',
            'additional_notes' => '',
            'transaction_date' => $order->date_created,
            'customer_group_id' => $customer->customer_group_id,
            'tax_rate_id' => null,
            'sale_note' => null,
            'commission_agent' => null,
            'invoice_no' => $order->number,
            'order_addresses' => json_encode($addresses),
            'shipping_charges' => ! empty($order->shipping_total) ? $order->shipping_total : 0,
            'shipping_details' => ! empty($shipping_lines_array) ? implode(', ', $shipping_lines_array) : '',
            'shipping_status' => $shipping_status,
            'shipping_address' => implode(', ', $shipping_address),
        ];
    
        $payment = [
            'amount' => $order->total,
            'method' => 'cash',
            'card_transaction_number' => '',
            'card_number' => '',
            'card_type' => '',
            'card_holder_name' => '',
            'card_month' => '',
            'card_security' => '',
            'cheque_number' => '',
            'bank_account_number' => '',
            'note' => $order->payment_method_title,
            'paid_on' => $order->date_paid,
        ];
    
        if (! empty($sell) && count($sell->payment_lines) > 0) {
            $payment['payment_id'] = $sell->payment_lines->first()->id;
        }
    
        $new_sell_data['products'] = $product_lines;
        $new_sell_data['payment'] = [$payment];
    
        return $new_sell_data;
    }
}
