<?php

return [
    'templates' => [
        'default_variant' => 'standard',
        'available_variants' => ['simple', 'standard', 'api-ready'],
    ],
    
    'crud' => [
        'default_module' => null,
        'auto_generate_code' => true,
        'default_separator' => '_',
    ],

    'paths' => [
        'templates' => __DIR__ . '/../resources/templates',
    ],
];
