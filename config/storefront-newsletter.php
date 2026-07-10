<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Storefront newsletter providers
    |--------------------------------------------------------------------------
    | Maps provider slug (stored in storefront settings) to driver class.
    */
    'drivers' => [
        'mailchimp' => \App\Services\Storefront\Newsletter\MailchimpNewsletterProvider::class,
        'mailerlite' => \App\Services\Storefront\Newsletter\MailerLiteNewsletterProvider::class,
        'aweber' => \App\Services\Storefront\Newsletter\AweberNewsletterProvider::class,
    ],

    'labels' => [
        'mailchimp' => 'Mailchimp',
        'mailerlite' => 'MailerLite',
        'aweber' => 'AWeber',
    ],
];
