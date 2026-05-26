<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_DRIVER', 'null'),



    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'useTLS' => true,
            ],
        ],

        /*'pusher_1' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'eu').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],


            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],
        'pusher_2' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY_2'),
            'secret' => env('PUSHER_APP_SECRET_2'),
            'app_id' => env('PUSHER_APP_ID_2'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER_2'),
                'host' => env('PUSHER_HOST') ?: 'api-' . env('PUSHER_APP_CLUSTER_2', 'eu') . '.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
        ],*/
        'pusher_notify' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY_NOTIFY'),
            'secret' => env('PUSHER_APP_SECRET_NOTIFY'),
            'app_id' => env('PUSHER_APP_ID_NOTIFY'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER_NOTIFY'),
                'host' => env('PUSHER_HOST') ?: 'api-' . env('PUSHER_APP_CLUSTER_NOTIFY', 'eu') . '.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
        ],

        'pusher_realtime' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY_REALTIME'),
            'secret' => env('PUSHER_APP_SECRET_REALTIME'),
            'app_id' => env('PUSHER_APP_ID_REALTIME'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER_REALTIME'),
                'host' => env('PUSHER_HOST') ?: 'api-' . env('PUSHER_APP_CLUSTER_REALTIME', 'eu') . '.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
        ],

        'pusher_list' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY_LIST'),
            'secret' => env('PUSHER_APP_SECRET_LIST'),
            'app_id' => env('PUSHER_APP_ID_LIST'),
            'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER_LIST'),
            'encrypted' => true,
            'useTLS' => true,
            'host' => 'api-eu.pusher.com',
            'port' => 443,
            'scheme' => 'https',
            ],
        ],
             'pusher_whatsapp' => [
        'driver' => 'pusher',
        'key' => env('PUSHER_APP_KEY_whtsp', 'c3e1f5ef9cde8f7376d2'),
        'secret' => env('PUSHER_APP_SECRET_whtsp', 'aae1889e06b26be769f8'),
        'app_id' => env('PUSHER_APP_ID_whtsp', '2070020'),
        'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER_whtsp', 'eu'),
            'encrypted' => true,
            'useTLS' => true,
        ],
        ],
         'pusher_document' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY_DOCUMENT'),
            'secret' => env('PUSHER_APP_SECRET_DOCUMENT'),
            'app_id' => env('PUSHER_APP_ID_DOCUMENT'),
            'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER_DOCUMENT'),
            'encrypted' => true,
            'useTLS' => true,
            'host' => 'api-eu.pusher.com',
            'port' => 443,
            'scheme' => 'https',
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
