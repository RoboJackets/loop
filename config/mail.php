<?php

declare(strict_types=1);

return [

    'mailers' => [
        'postmark' => [
            'transport' => 'postmark',
            'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'message_stream_id' => null,
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'mailgun' => [
            'transport' => 'mailgun',
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
        ],
    ],

];
