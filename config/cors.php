<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        '*', // 🔥 allow CORS for web routes like /login
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
         'http://localhost:5174',
    ],

    'allowed_headers' => ['*'],

    'supports_credentials' => true,

];
