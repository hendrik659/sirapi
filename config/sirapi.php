<?php

return [

    'initial_admin_setup' => [

        'enabled' => env(
            'INITIAL_ADMIN_SETUP_ENABLED',
            false
        ),

        'code_hash' => env(
            'INITIAL_ADMIN_SETUP_CODE_HASH'
        ),

    ],

];
