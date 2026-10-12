<?php

declare(strict_types=1);

return [
    // Comma-separated addresses or CIDR ranges of the HTTPS proxy in front of the application; "*" trusts every sender.
    'proxies' => env('TRUSTED_PROXIES'),
];
