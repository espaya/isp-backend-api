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
         'http://192.168.10.10:5173', // temporary IP address for local testing
         'https://novanetgh.net',
         'https://admin.novanetgh.net'
    ],

    'allowed_headers' => ['*'],

    'supports_credentials' => true,

];
