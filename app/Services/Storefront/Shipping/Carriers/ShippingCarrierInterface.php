<?php

namespace App\Services\Storefront\Shipping\Carriers;

use App\Transaction;

interface ShippingCarrierInterface
{
    public function key(): string;

    public function isEnabled(int $businessId): bool;

    /**
     * @return array{tracking_number:?string,tracking_url:?string,external_id:?string,label_url:?string,meta?:array}|null
     */
    public function createShipment(int $businessId, Transaction $transaction): ?array;

    /**
     * @return array{status:?string,tracking_number:?string,tracking_url:?string}|null
     */
    public function getTracking(int $businessId, string $externalId): ?array;
}
