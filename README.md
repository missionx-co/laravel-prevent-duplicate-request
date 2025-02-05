# Laravel Prevent Duplicate Requests

[![Latest Version on Packagist](https://img.shields.io/packagist/v/missionx-co/laravel-prevent-duplicate-request.svg?style=flat-square)](https://packagist.org/packages/missionx-co/laravel-prevent-duplicate-request)
[![Total Downloads](https://img.shields.io/packagist/dt/missionx-co/laravel-prevent-duplicate-request.svg?style=flat-square)](https://packagist.org/packages/missionx-co/laravel-prevent-duplicate-request)

A Laravel package to prevent duplicate API requests using either Idempotency Keys or dynamically generated keys based on the request URL and input.

## Installation

Install the package via Composer:

```bash
composer require missionx-co/laravel-prevent-duplicate-request
```

### Publish the configuration file

```bash
php artisan vendor:publish --tag="prevent-duplicate-request-config"
```

## Usage

### Middleware Setup

Add the `preventDuplicateRequests` middleware to your API middleware group

**Laravel 11**

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(append: [
        'preventDuplicateRequests'
    ]);
})
```

**Laravel 10**

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ...
    ],

    'api' => [
        // ...
        'preventDuplicateRequests',
    ],
];
```

### Key Generation Algorithms

To prevent duplicate API requests, you can choose between two algorithms:

1. **Idempotency Key**:
   Use `\MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms\IdempotencyKey::class` to uniquely identify and deduplicate requests, ensuring the same key results in the same response.
   This requires the client to send an Idempotency-Key header with each request.

2. **Request Fingerprint**:
   Use `\MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms\RequestFingerprintGenerator::class` to automatically generate a unique key based on the request URL and input.
   This is useful when you don't want to rely on the client to provide a key.

#### Configuring the Algorithm

Set the desired key generation algorithm in the configuration file (`config/prevent-duplicate-request.php`):

```php
return [
    'key_algorithm' => \MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms\RequestFingerprintGenerator::class,
];
```
