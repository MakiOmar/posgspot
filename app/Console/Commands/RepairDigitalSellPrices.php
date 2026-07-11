<?php

namespace App\Console\Commands;

use App\Services\Storefront\DigitalFulfillmentService;
use Illuminate\Console\Command;

/**
 * Repair storefront digital sales that were saved with L.E. 0.00 line prices.
 */
class RepairDigitalSellPrices extends Command
{
    protected $signature = 'storefront:repair-digital-prices {--transaction= : Optional transaction id}';

    protected $description = 'Repair zero-price digital storefront sell lines from fulfillment request_meta';

    public function handle(DigitalFulfillmentService $fulfillment): int
    {
        $txId = $this->option('transaction');
        $fixed = $fulfillment->repairZeroPriceDigitalOrders(
            $txId !== null && $txId !== '' ? (int) $txId : null
        );
        $this->info("Repaired {$fixed} digital order(s).");

        return self::SUCCESS;
    }
}
