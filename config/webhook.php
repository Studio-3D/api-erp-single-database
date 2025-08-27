<?php

return [
    // Base URL used to build webhook callback URLs returned to the frontend/console setups
    // Example: https://278a22824994.ngrok-free.app or http://localhost:8000
    'base_url' => env('WEBHOOK_BASE_URL', env('APP_URL', 'http://localhost:8000')),
];

