<?php

namespace App\Console\Commands;

use App\Services\Accounts\SalesOrderDigitalFulfillmentService;
use Illuminate\Console\Command;

/**
 * Retries Accounts allocation for call-center sales orders (Accounts down, out of stock, crash).
 */
class FulfillSalesOrderAccounts extends Command
{
    protected $signature = 'accounts:fulfill-sales-orders {--transaction= : Optional sales order id}';

    protected $description = 'Retry failed Accounts game account / card allocations on POS sales orders';

    public function handle(SalesOrderDigitalFulfillmentService $fulfilment): int
    {
        $id = $this->option('transaction');
        $allocated = $fulfilment->retry($id !== null && $id !== '' ? (int) $id : null);
        $this->info("Allocated {$allocated} account line(s).");

        return self::SUCCESS;
    }
}
