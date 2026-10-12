<?php

declare(strict_types=1);

return [
    'enabled' => env('REALTIME_ENABLED', false),
    'public_port' => (int) env('REVERB_PUBLIC_PORT', 8089),
    'secure_public_port' => (int) env('REVERB_SECURE_PUBLIC_PORT', 443),
];
