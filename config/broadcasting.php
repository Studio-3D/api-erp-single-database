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
   'default' => env('BROADCAST_CONNECTION', 'pusher_6'),

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

        'pusher_1' => [
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
        ],
        'pusher_3' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY_3'),
            'secret' => env('PUSHER_APP_SECRET_3'),
            'app_id' => env('PUSHER_APP_ID_3'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER_3'),
                'host' => env('PUSHER_HOST') ?: 'api-' . env('PUSHER_APP_CLUSTER_3', 'eu') . '.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
        ],
        'pusher_5' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY_5'),
            'secret' => env('PUSHER_APP_SECRET_5'),
            'app_id' => env('PUSHER_APP_ID_5'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER_5'),
                'host' => env('PUSHER_HOST') ?: 'api-' . env('PUSHER_APP_CLUSTER_5', 'eu') . '.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
        ],
        'pusher_4' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY_4'),
            'secret' => env('PUSHER_APP_SECRET_4'),
            'app_id' => env('PUSHER_APP_ID_4'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER_4'),
                'host' => env('PUSHER_HOST') ?: 'api-' . env('PUSHER_APP_CLUSTER_4', 'eu') . '.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
        ],
         'pusher_6' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY_6'),
            'secret' => env('PUSHER_APP_SECRET_6'),
            'app_id' => env('PUSHER_APP_ID_6'),
            'options' => [
            'cluster' => env('PUSHER_APP_CLUSTER_6'),
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
