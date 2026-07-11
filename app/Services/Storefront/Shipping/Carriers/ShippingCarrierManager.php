<?php

namespace App\Services\Storefront\Shipping\Carriers;

use App\Transaction;

class ShippingCarrierManager
{
    /** @var ShippingCarrierInterface[] */
    private array $carriers;

    public function __construct(BostaShippingCarrier $bosta)
    {
        $this->carriers = [$bosta];
    }

    public function driver(string $key): ?ShippingCarrierInterface
    {
        foreach ($this->carriers as $carrier) {
            if ($carrier->key() === $key) {
                return $carrier;
            }
        }

        return null;
    }

    /**
     * @return array{tracking_number:?string,tracking_url:?string,external_id:?string,label_url:?string}|null
     */
    public function createForTransaction(int $businessId, Transaction $transaction, string $carrier = 'bosta'): ?array
    {
        $driver = $this->driver($carrier);
        if (! $driver || ! $driver->isEnabled($businessId)) {
            return null;
        }

        return $driver->createShipment($businessId, $transaction);
    }
}
