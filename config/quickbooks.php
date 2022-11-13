<?php

declare(strict_types=1);

return [
    'client' => [
        'id' => env('QUICKBOOKS_CLIENT_ID'),
        'secret' => env('QUICKBOOKS_CLIENT_SECRET'),
    ],
    'environment' => env('QUICKBOOKS_ENVIRONMENT'),
    'company' => [
        'id' => env('QUICKBOOKS_COMPANY_ID'),
    ],
    'invoice' => [
        'item_id' => env('QUICKBOOKS_INVOICE_ITEM_ID'),
        'customer_id' => env('QUICKBOOKS_INVOICE_CUSTOMER_ID'),
    ],
];
