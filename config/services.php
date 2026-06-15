<?php

return [
    'currency' => [
        'code' => 'CNY',
        'symbol' => '¥',
    ],

    'ai_http' => [
        'verify_ssl' => env('AI_HTTP_VERIFY_SSL', true),
        'ca_bundle' => env('AI_HTTP_CA_BUNDLE'),
        'use_native_ca' => env('AI_HTTP_USE_NATIVE_CA', true),
        'responses_image_model' => env('AI_RESPONSES_IMAGE_MODEL', 'gpt-5.5'),
    ],
];
