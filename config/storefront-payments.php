<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storefront payment gateway drivers
    |--------------------------------------------------------------------------
    | Maps provider slug (stored in storefront settings) to driver class.
    */
    'drivers' => [
        'fawry' => \App\Services\Storefront\Payment\FawryPaymentGateway::class,
    ],

    'labels' => [
        'fawry' => 'FawryPay',
    ],
];
