<?php

namespace App\Services\Storefront\Shipping;

use App\StorefrontShippingMethod;

interface ShippingMethodCalculator
{
    public function supports(string $type): bool;

    /**
     * @param  array{subtotal:float,item_count:float,locale:string,destination:array,pickup_location_id:?int,shipping_class_costs?:array<int,float>,cart_weight?:float}  $context
     * @return array{amount:float,eta_label:?string,meta:array}|null  null = not available
     */
    public function quote(StorefrontShippingMethod $method, array $context): ?array;
}
