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

    'local_ai' => [
        'runner_url' => env('LOCAL_AI_RUNNER_URL'),
        'fallback_enabled' => env('LOCAL_AI_FALLBACK_ENABLED', true),
        'memory_guard_enabled' => env('LOCAL_AI_MEMORY_GUARD_ENABLED', true),
        'max_memory_percent' => env('LOCAL_AI_MAX_MEMORY_PERCENT', 85),
        'min_free_memory_mb' => env('LOCAL_AI_MIN_FREE_MEMORY_MB', 1024),
        'stop_url' => env('LOCAL_AI_STOP_URL'),
        'stop_timeout' => env('LOCAL_AI_STOP_TIMEOUT', 5),
        'cooldown_seconds' => env('LOCAL_AI_COOLDOWN_SECONDS', 600),
    ],

    'payment_proof_ocr' => [
        'binary' => env('PAYMENT_PROOF_OCR_BINARY', 'tesseract'),
        'languages' => env('PAYMENT_PROOF_OCR_LANGUAGES', 'chi_sim+eng'),
        'timeout' => env('PAYMENT_PROOF_OCR_TIMEOUT', 10),
    ],
];
