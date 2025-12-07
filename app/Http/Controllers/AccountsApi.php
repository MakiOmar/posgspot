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
use App\Contact;
use App\Exceptions\PurchaseSellMismatch;
use App\Product;
use App\Utils\ContactUtil;
use App\Utils\Util;
use App\VariationLocationDetails;
use App\Utils\CommonUtil;
use App\Events\ContactCreatedOrModified;
use Illuminate\Support\Facades\Http;

class AccountsApi extends Controller
{
    /**
     * Constructor
     *
     * @param  WoocommerceUtil  $woocommerceUtil
     * @return void
     */
    public function __construct(TransactionUtil $transactionUtil)
    {
        $this->transactionUtil = $transactionUtil;
    }
    public function orderCreated(Request $request, $business_id)
    {
        try {
            $payload = $request->getContent();

            $business = Business::findOrFail($business_id);
            $user_id = $business->owner->id;
            $order_data = json_decode($payload);
            $business_data = [
                'id' => $business_id,
                'accounting_method' => $business->accounting_method,
                'location_id' => $order_data->location_id,
                'business' => $business,
            ];

            DB::beginTransaction();
            $created = $this->createNewSaleFromOrder($business_id, $user_id, $order_data, $business_data);
            $create_error_data = $created !== true ? $created : [];
            DB::commit();
            return response()->json([
                'message' => 'Order has been created successfully',
                'created' => $created
            ], 200);
        } catch (\Exception $e) {
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
        
        // Set created_at and updated_at to match transaction_date
        $transaction_date = \Carbon\Carbon::parse($transaction->transaction_date);
        $transaction->created_at = $transaction_date;
        $transaction->updated_at = $transaction_date;
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
            // Keep updated_at matching transaction_date
            $transaction->updated_at = $transaction_date;
            $transaction->save();

            try {
                $this->transactionUtil->mapPurchaseSell($business_data, $transaction->sell_lines, 'purchase');
            } catch (PurchaseSellMismatch $e) {
                DB::rollBack();

                return [
                    'error_type' => 'order_insuficient_product_qty',
                    'order_number' => $order->number ?? $order->id ?? 'N/A',
                    'msg' => $e->getMessage(),
                ];
            }
        }

        DB::commit();

        return $transaction;
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
        $sell_line_note = '';
        foreach ($order->line_items as $product_line) {
            $game_title = null;
            $account = null;
            $password = null;
            $type = null;
            $pos_product_id = null;
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
                    } elseif ($meta->key === '_pos_product_id') {
                        $pos_product_id = $meta->value;
                    }
                }
            }

            $sell_line_note .= "\nGame Title: " . ($game_title ?? 'N/A') . "\nType: " . ($type ?? 'N/A') . "\nAccount: " . ($account ?? 'N/A') . "\nPassword: " . ($password ?? 'N/A') . "<br>----------------------<br>";

            $product_line_product_id = $pos_product_id;
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
                        'order_number' => $order->number ?? $order->id ?? 'N/A',
                        'product' => $product_line->name . ' SKU:' . $product_line->sku,
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
                    'line_item_id' => $product_line->id ?? null,
                ];

                $product_lines[] = $product_data;
            } else {
                return ['has_error' => [
                    'error_type' => 'order_product_not_found',
                    'order_number' => $order->number ?? $order->id ?? 'N/A',
                    'product' => $product_line->name . ' SKU:' . $product_line->sku,
                ],
                ];
                exit;
            }
        }

        $billing = $order->billing ?? (object)[];
        $f_name = ! empty($billing->first_name) ? $billing->first_name : '';
        $l_name = ! empty($billing->last_name) ? $billing->last_name : '';
        $customer_details = [
            'first_name' => $f_name,
            'last_name' => $l_name,
            'email' => ! empty($billing->email) ? $billing->email : null,
            'name' => $f_name . ' ' . $l_name,
            'mobile' => $billing->phone ?? null,
            'address_line_1' => ! empty($billing->address_1) ? $billing->address_1 : null,
            'address_line_2' => ! empty($billing->address_2) ? $billing->address_2 : null,
            'city' => ! empty($billing->city) ? $billing->city : null,
            'state' => ! empty($billing->state) ? $billing->state : null,
            'country' => ! empty($billing->country) ? $billing->country : null,
            'zip_code' => ! empty($billing->postcode) ? $billing->postcode : null,
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

        $sell_status = 'quotation';
        $shipping_status = 'ordered';
        $shipping = $order->shipping ?? (object)[];
        $shipping_address = [];
        if (! empty($shipping->first_name)) {
            $shipping_address[] = ($shipping->first_name ?? '') . ' ' . ($shipping->last_name ?? '');
        }
        if (! empty($shipping->company)) {
            $shipping_address[] = $shipping->company;
        }
        if (! empty($shipping->address_1)) {
            $shipping_address[] = $shipping->address_1;
        }
        if (! empty($shipping->address_2)) {
            $shipping_address[] = $shipping->address_2;
        }
        if (! empty($shipping->city)) {
            $shipping_address[] = $shipping->city;
        }
        if (! empty($shipping->state)) {
            $shipping_address[] = $shipping->state;
        }
        if (! empty($shipping->country)) {
            $shipping_address[] = $shipping->country;
        }
        if (! empty($shipping->postcode)) {
            $shipping_address[] = $shipping->postcode;
        }
        $addresses['shipping_address'] = [
            'shipping_name' => ($shipping->first_name ?? '') . ' ' . ($shipping->last_name ?? ''),
            'company' => $shipping->company ?? null,
            'shipping_address_line_1' => $shipping->address_1 ?? null,
            'shipping_address_line_2' => $shipping->address_2 ?? null,
            'shipping_city' => $shipping->city ?? null,
            'shipping_state' => $shipping->state ?? null,
            'shipping_country' => $shipping->country ?? null,
            'shipping_zip_code' => $shipping->postcode ?? null,
        ];
        $addresses['billing_address'] = [
            'billing_name' => ($billing->first_name ?? '') . ' ' . ($billing->last_name ?? ''),
            'company' => $billing->company ?? null,
            'billing_address_line_1' => $billing->address_1 ?? null,
            'billing_address_line_2' => $billing->address_2 ?? null,
            'billing_city' => $billing->city ?? null,
            'billing_state' => $billing->state ?? null,
            'billing_country' => $billing->country ?? null,
            'billing_zip_code' => $billing->postcode ?? null,
        ];

        $shipping_lines_array = [];
        if (! empty($order->shipping_lines)) {
            foreach ($order->shipping_lines as $shipping_lines) {
                $shipping_lines_array[] = $shipping_lines->method_title;
            }
        }

        $new_sell_data = [
            'business_id' => $business_id,
            'location_id' => $order->location_id,
            'contact_id' => $customer->id,
            'discount_type' => 'fixed',
            'discount_amount' => $order->discount_total,
            'shipping_charges' => $order->shipping_total,
            'final_total' => $order->total,
            'created_by' => $user_id,
            'status' => $sell_status == 'quotation' ? 'draft' : $sell_status,
            'is_quotation' => $sell_status == 'quotation' ? 1 : 0,
            'sub_status' => $sell_status == 'quotation' ? 'quotation' : null,
            'payment_status' => 'due',
            'additional_notes' => '',
            'transaction_date' => $order->date_created,
            'customer_group_id' => $customer->customer_group_id,
            'tax_rate_id' => null,
            'sale_note' => null,
            'staff_note' => $sell_line_note,
            'commission_agent' => null,
            'invoice_no' => $order->number ?? $order->id ?? null,
            'order_addresses' => json_encode($addresses),
            'shipping_charges' => ! empty($order->shipping_total) ? $order->shipping_total : 0,
            'shipping_details' => ! empty($shipping_lines_array) ? implode(', ', $shipping_lines_array) : '',
            'shipping_status' => $shipping_status,
            'shipping_address' => implode(', ', $shipping_address),
            'custom_field_1' => 'account_order',
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
    public function createContact(Request $request)
    {
        $data = $request->validate([
        'billing.first_name' => 'required|string',
        'billing.last_name' => 'nullable|string',
        'billing.email' => 'nullable|email',
        'billing.phone' => 'required|string', // ضروري للتحقق
        'billing.address_1' => 'nullable|string',
        'billing.address_2' => 'nullable|string',
        'billing.city' => 'nullable|string',
        'billing.state' => 'nullable|string',
        'billing.postcode' => 'nullable|string',
        'billing.country' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $business_id = 1;
            $user_id = 1;
            $billing = $data['billing'];

            $customer_details = [
            'first_name' => $billing['first_name'],
            'last_name' => $billing['last_name'] ?? '',
            'email' => $billing['email'] ?? '',
            'mobile' => $billing['phone'],
            'city' => $billing['city'] ?? '',
            'state' => $billing['state'] ?? '',
            'country' => $billing['country'] ?? '',
            'address_line_1' => $billing['address_1'] ?? '',
            'address_line_2' => $billing['address_2'] ?? '',
            'zip_code' => $billing['postcode'] ?? '',
            ];

            $customer_details['name'] = trim($customer_details['first_name'] . ' ' . $customer_details['last_name']);

            // 🔍 Check if customer exists using mobile only
            $customer = \App\Contact::where('business_id', $business_id)
                        ->where('mobile', $customer_details['mobile'])
                        ->OnlyCustomers()
                        ->first();

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

                if (empty(trim($customer_data['name']))) {
                    $customer_data['first_name'] = $customer_details['email'];
                    $customer_data['name'] = $customer_details['email'];
                }

                $customer = \App\Contact::create($customer_data);
            }

            DB::commit();

            return response()->json([
            'success' => true,
            'contact_id' => $customer->id,
            'msg' => 'Customer created successfully',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error("Error creating contact: " . $e->getMessage());

            return response()->json([
            'success' => false,
            'msg' => 'Something went wrong.',
            ], 500);
        }
    }

    public function getOrdersByPhone(Request $request)
    {
        $request->validate([
        'phone' => 'required|string',
        ]);

        $phone = $request->input('phone');

    // البحث عن العميل حسب رقم الهاتف
        $customer = Contact::where('mobile', $phone)->orWhere('mobile', 'like', "%$phone%")->first();

        if (! $customer) {
            return response()->json([
            'status' => false,
            'message' => 'العميل غير موجود',
            ], 404);
        }

    // جلب الطلبات (transactions) المرتبطة بالعميل
        $orders = Transaction::where('contact_id', $customer->id)
        ->where('type', 'sell')
        ->select('id', 'invoice_no', 'transaction_date', 'final_total', 'payment_status', 'status')
        ->orderBy('transaction_date', 'desc')
        ->get();

        return response()->json([
        'status' => true,
        'customer' => [
            'name' => $customer->name,
            'mobile' => $customer->mobile,
        ],
        'orders' => $orders,
        ]);
    }
}
