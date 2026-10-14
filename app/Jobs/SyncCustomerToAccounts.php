<?php

namespace App\Jobs;

use App\Services\Storefront\Accounts\AccountsCustomerSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Retry an Accounts find-or-create that could not run during signup (Accounts down).
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

    public function handle(AccountsCustomerSync $sync): void
    {
        if (! $sync->isConfigured()) {
            return;
        }

        if ($sync->ensure($this->name, $this->email, $this->phone) === AccountsCustomerSync::UNAVAILABLE) {
            throw new \RuntimeException('Accounts customer sync unavailable for '.$this->phone);
        }
    }
}
