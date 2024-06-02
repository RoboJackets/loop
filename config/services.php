<?php

declare(strict_types=1);

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'sensible' => [
        'url' => env('SENSIBLE_URL'),
        'bank_statements_url' => env('SENSIBLE_BANK_STATEMENTS_URL'),
        'token' => env('SENSIBLE_TOKEN'),
    ],

    'tika' => [
        'url' => env('TIKA_URL'),
    ],

    'mercury' => [
        'transactions_url' => env('MERCURY_TRANSACTIONS_URL'),
        'token' => env('MERCURY_TOKEN'),
    ],

    'treasurer_email_address' => env('TREASURER_EMAIL_ADDRESS'),

    'treasurer_name' => env('TREASURER_NAME'),

    'developer_email_address' => env('DEVELOPER_EMAIL_ADDRESS'),

];
