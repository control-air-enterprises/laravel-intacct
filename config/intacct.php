<?php

declare(strict_types=1);

return [
    'default' => env('SAGE_INTACCT_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'client_id' => env('SAGE_INTACCT_CLIENT_ID'),
            'client_secret' => env('SAGE_INTACCT_CLIENT_SECRET'),
            'company_id' => env('SAGE_INTACCT_COMPANY_ID'),
            'user_id' => env('SAGE_INTACCT_USER_ID'),
            'entity_id' => env('SAGE_INTACCT_ENTITY_ID'),
            'token_key' => env('SAGE_INTACCT_TOKEN_KEY', 'default'),
        ],
    ],

    'tokens' => [
        'driver' => env('SAGE_INTACCT_TOKEN_STORE', 'database'),
    ],
];
