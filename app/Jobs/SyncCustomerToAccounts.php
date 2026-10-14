<?php

namespace App\Jobs;

use App\Services\Storefront\Accounts\AccountsApiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Ensure a POS customer also exists in Accounts (find-or-create by phone).
 */
class SyncCustomerToAccounts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(
        public string $name,
        public string $email,
        public string $phone
    ) {
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function handle(AccountsApiClient $accounts): void
    {
        if ($accounts->baseUrl() === '') {
            Log::warning('Accounts customer sync skipped: ACCOUNTS_BASE_URL is empty', ['phone' => $this->phone]);

            return;
        }

        $result = $accounts->createCustomer($this->name, $this->email, $this->phone);
        if ($result['success']) {
            return;
        }

        // 422 = Accounts rejected the data (e.g. email owned by another phone); retrying cannot fix it.
        if ($result['status'] === 422) {
            Log::warning('Accounts customer sync rejected', [
                'phone' => $this->phone,
                'email' => $this->email,
                'error' => $result['error'],
            ]);

            return;
        }

        throw new \RuntimeException('Accounts customer sync failed (HTTP '.$result['status'].'): '.$result['error']);
    }
}
