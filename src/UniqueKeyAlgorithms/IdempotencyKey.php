<?php

namespace MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class IdempotencyKey extends UniqueKeyAlgorithm
{
    private string $key;

    public static string $headerName = 'Idempotency-Key';

    public static function idempotencyHeader(string $headerName)
    {
        static::$headerName = $headerName;
    }

    public function name(): string
    {
        return 'idempotency_key';
    }

    public function handle(Request $request): string
    {
        if (! isset($this->key)) {
            $this->key = $request->header(static::$headerName);
        }

        return $this->key;
    }

    public function handleCachedResponse(Response $response)
    {
        $response->header('Idempotency-Relayed', $this->key);
    }
}
