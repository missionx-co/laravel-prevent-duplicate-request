<?php

namespace MissionX\LaravelPreventDuplicateRequest\Middlewares;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use MissionX\LaravelPreventDuplicateRequest\Exceptions\MismatchedPathException;
use MissionX\LaravelPreventDuplicateRequest\Exceptions\MissingIdempotencyKeyException;
use MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms\UniqueKeyAlgorithm;
use Symfony\Component\HttpFoundation\Response;

class PreventDuplicateRequestMiddleware
{
    protected Lock $lock;

    protected static $resolveUserIdUsing;

    public static function removeUserIdUsing(callable $callback)
    {
        static::$resolveUserIdUsing = $callback;
    }

    public function handle(Request $request, Closure $next, ?int $maxWaitTime = null): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $maxWaitTime ??= config('prevent-duplicate-request.max_lock_wait_time');

        $key = $this->generateKey($request);

        $this->lock = $this->store()->lock($key . '_lock', $maxWaitTime);

        $this->lock->block($maxWaitTime);

        if ($this->store()->has($key)) {
            return $this->buildResponseFromCache($request, $this->store()->get($key));
        }

        $response = $next($request);
        $this->saveResponseInCache($key, $request, $response);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        if (isset($this->lock)) {
            $this->lock->release();
        }
    }

    protected function generateKey(Request $request): string
    {
        $key = $this->algorithm()->handle($request);
        if (! $key) {
            throw new MissingIdempotencyKeyException;
        }

        $userId = $this->resolveUserId($request);

        return config('prevent-duplicate-request.cache_prefix') . '_' . $userId . '_' . $key;
    }

    protected function resolveUserId(Request $request)
    {
        if (isset(static::$resolveUserIdUsing)) {
            return call_user_func(static::$resolveUserIdUsing, $request);
        }

        if ($request->user()) {
            return $request->user()->getKey();
        }

        return $request->ip();
    }

    protected function saveResponseInCache(string $key, Request $request, Response $response)
    {
        $data = [
            'path' => $request->path(),
            'body' => $response->getContent(),
            'status' => $response->getStatusCode(),
            'headers' => $response->headers->all(),
        ];

        $this->store()->put($key, $data, config('prevent-duplicate-request.cache_duration'));
    }

    public function buildResponseFromCache(Request $request, array $responseInCache)
    {
        if ($request->path() != $responseInCache['path']) {
            throw new MismatchedPathException;
        }

        $response = response($responseInCache['body'], $responseInCache['status'])
            ->withHeaders($responseInCache['headers']);

        $this->algorithm()->handleCachedResponse($response);

        return $response;
    }

    protected function store(): Repository
    {
        return once(fn() => Cache::store(config('prevent-duplicate-request.cache_store')));
    }

    protected function algorithm(): UniqueKeyAlgorithm
    {
        return once(fn() => app(config('prevent-duplicate-request.key_algorithm')));
    }
}
