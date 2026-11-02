<?php

declare(strict_types=1);

return [
    // API-wide rate limit for /api/v1 (requests per minute per user/IP).
    // env() lives only here — routes read config() so config:cache keeps working.
    'throttle_max' => (int) env('API_THROTTLE_MAX', 120),
];
