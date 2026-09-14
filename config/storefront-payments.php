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
        'geidea' => \App\Services\Storefront\Payment\GeideaPaymentGateway::class,
    ],

    'labels' => [
        'fawry' => 'FawryPay',
        'geidea' => 'Geidea',
    ],
];
