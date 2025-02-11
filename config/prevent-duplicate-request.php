<?php

// config for MissionX/LaravelPreventDuplicateRequest
return [
    'cache_prefix' => env('PREVENT_DUPLICATE_REQUEST_CACHE_PREFIX', 'prevent_duplicate_request'),

    /**
     * To prevent duplicate API requests, you can choose between two algorithms
     * 1. **Idempotency Key**: Use a pre-defined `idempotency_key` to uniquely identify and deduplicate requests, ensuring the same key results in the same response.
     * 2. **Request Fingerprint**: Automatically generate a unique key based on the request URL and input, creating a `request_fingerprint` to detect and prevent duplicates.
     *
     *  other values: \MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms\IdempotencyKey
     */
    'key_algorithm' => \MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms\RequestFingerprintGenerator::class,

    /**
     * The cache store used to save the response of the request
     *
     * Make sure this cache store supports lock
     *
     * @see https://laravel.com/docs/11.x/cache#atomic-locks
     */
    'cache_store' => 'redis',

    'cache_duration' => 1800, // 30 minutes in seconds

    'cache_tags' => 'laravel_prevent_duplicate_requests',

    // When a race condition happens, we create a cache lock. How long should the lock persist?
    'max_lock_wait_time' => 10,
];
