<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('PUBLIC_DISK_URL', '/storage'),
            'visibility' => 'public',
            'throw' => false,
        ],
        'public_uploads' => [
            'driver' => 'local',
            'root' => public_path('uploads'),
            'url' => env('PUBLIC_UPLOADS_URL', '/uploads'),
            'visibility' => 'public',
            'throw' => false,
        ],
        'payment_proofs' => [
            'driver' => 'local',
            'root' => storage_path('app/private/payment-proofs'),
            'visibility' => 'private',
            'throw' => false,
        ],
        'support_attachments' => [
            'driver' => 'local',
            'root' => storage_path('app/private/support-attachments'),
            'visibility' => 'private',
            'throw' => false,
        ],
        'private_attachments' => [
            'driver' => 'local',
            'root' => storage_path('app/private/private-attachments'),
            'visibility' => 'private',
            'throw' => false,
        ],
        'digital_deliveries' => [
            'driver' => 'local',
            'root' => storage_path('app/private/digital-deliveries'),
            'visibility' => 'private',
            'throw' => false,
        ],
    ],
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
