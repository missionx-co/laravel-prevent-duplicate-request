<?php

namespace MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms;

use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

abstract class UniqueKeyAlgorithm
{
    public function name(): string
    {
        return static::class;
    }

    public function handleResponse(Response $response) {}

    public function handleCachedResponse(HttpResponse $response) {}

    abstract public function handle(Request $request): string;
}
