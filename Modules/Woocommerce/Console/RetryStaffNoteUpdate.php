<?php

namespace Modules\Woocommerce\Console;

use App\Business;
use App\Transaction;
use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RetryStaffNoteUpdate extends Command
{
    protected $signature = 'woocommerce:retry-staff-notes {business_id?} {--limit=50}';
    protected $description = 'Retry updating staff notes that contain N/A from WooCommerce';

    public function handle()
    {
        $business_id = $this->argument('business_id');
        $limit = $this->option('limit');

        if ($business_id) {
            $this->processBusinessOrders($business_id, $limit);
        } else {
            // Process all businesses with WooCommerce enabled
            $businesses = Business::whereNotNull('woocommerce_api_settings')->get();
            
            foreach ($businesses as $business) {
                $this->info("Processing business #{$business->id} - {$business->name}");
                $this->processBusinessOrders($business->id, $limit);
            }
        }

        $this->info('Done!');
        return 0;
    }

    private function processBusinessOrders($business_id, $limit)
    {
        try {
            $business = Business::findOrFail($business_id);
            $api_settings = json_decode($business->woocommerce_api_settings);

            if (empty($api_settings)) {
                $this->error("Business #{$business_id} has no WooCommerce settings");
                return;
            }

            // Get webhook secret from environment variable
            $webhook_secret = env('WOOCOMMERCE_WEBHOOK_SECRET');
            
            if (empty($webhook_secret)) {
                $this->error("WOOCOMMERCE_WEBHOOK_SECRET not defined in .env file");
                $this->info("Add WOOCOMMERCE_WEBHOOK_SECRET=your_secret to your .env file");
                return;
            }

            // Find orders with N/A in staff_note
            $transactions = Transaction::where('business_id', $business_id)
                ->whereNotNull('woocommerce_order_id')
                ->where(function($query) {
                    $query->whereNull('staff_note')
                          ->orWhere('staff_note', 'like', '%N/A%');
                })
                ->limit($limit)
                ->get();

            if ($transactions->isEmpty()) {
                $this->info("No orders with N/A found for business #{$business_id}");
                return;
            }

            $this->info("Found {$transactions->count()} orders to update");

            $success_count = 0;
            $failed_count = 0;

            foreach ($transactions as $transaction) {
                $result = $this->updateStaffNote($business, $transaction, $webhook_secret);
                
                if ($result['success']) {
                    $success_count++;
                    $this->info("✓ Order #{$transaction->woocommerce_order_id} updated");
                } else {
                    $failed_count++;
                    $this->error("✗ Order #{$transaction->woocommerce_order_id} failed: {$result['message']}");
                }

                // Small delay to avoid rate limiting
                usleep(500000); // 0.5 seconds
            }

            $this->info("Success: {$success_count}, Failed: {$failed_count}");

        } catch (\Exception $e) {
            $this->error("Error processing business #{$business_id}: {$e->getMessage()}");
            Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
        }
    }

    private function updateStaffNote($business, $transaction, $webhook_secret)
    {
        $api_settings = json_decode($business->woocommerce_api_settings);
        $woocommerce_url = rtrim($api_settings->woocommerce_url ?? '', '/');
        $order_id = $transaction->woocommerce_order_id;

        if (empty($woocommerce_url)) {
            return [
                'success' => false,
                'message' => 'WooCommerce URL not configured'
            ];
        }

        // Build REST API endpoint
        $endpoint = "{$woocommerce_url}/wp-json/gamesspot/v1/update-staff-note/{$order_id}";

        try {
            // Make request with retry logic
            $max_attempts = 3;
            $attempt = 0;
            $last_error = null;

            while ($attempt < $max_attempts) {
                $attempt++;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $endpoint);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Bearer ' . $webhook_secret,
                    'Content-Type: application/json',
                ]);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'order_id' => $order_id
                ]));
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                if ($curl_error) {
                    $last_error = "cURL error: {$curl_error}";
                    
                    if ($attempt < $max_attempts) {
                        // Exponential backoff: 1s, 2s, 4s
                        sleep(pow(2, $attempt - 1));
                        continue;
                    }
                } elseif ($http_code >= 200 && $http_code < 300) {
                    // Success!
                    $response_data = json_decode($response, true);
                    
                    // Update local staff_note if returned
                    if (isset($response_data['staff_note'])) {
                        $transaction->staff_note = $response_data['staff_note'];
                        $transaction->save();
                    }

                    Log::info("Successfully updated staff note for order #{$order_id}");

                    return [
                        'success' => true,
                        'message' => 'Updated successfully',
                        'response' => $response_data
                    ];
                } elseif ($http_code >= 500 && $attempt < $max_attempts) {
                    // Server error, retry
                    $last_error = "HTTP {$http_code}: Server error";
                    sleep(pow(2, $attempt - 1));
                    continue;
                } else {
                    // Client error (4xx) or final attempt, don't retry
                    $last_error = "HTTP {$http_code}: " . substr($response, 0, 200);
                    break;
                }
            }

            // All attempts failed
            Log::error("Failed to update staff note for order #{$order_id} after {$attempt} attempts", [
                'last_error' => $last_error,
                'business_id' => $business->id,
            ]);

            return [
                'success' => false,
                'message' => $last_error ?? 'Unknown error'
            ];

        } catch (\Exception $e) {
            Log::error("Exception updating staff note for order #{$order_id}: {$e->getMessage()}");
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

