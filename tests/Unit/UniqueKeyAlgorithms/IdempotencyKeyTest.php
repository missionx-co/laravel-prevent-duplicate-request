<?php

namespace MissionX\LaravelPreventDuplicateRequest\Tests\Unit\UniqueKeyAlgorithms;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use MissionX\LaravelPreventDuplicateRequest\Tests\TestCase;
use MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms\IdempotencyKey;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class IdempotencyKeyTest extends TestCase
{
    #[Test]
    public function it_extracts_key_using_default_header_name()
    {
        $key = Str::random();
        $request = $this->mock(Request::class, function (MockInterface $mock) use ($key) {
            $mock->shouldReceive('header')->with('Idempotency-Key')->once()->andReturn($key);
        });

        $this->assertEquals($key, (new IdempotencyKey)->handle($request));
    }

    #[Test]
    public function it_extracts_using_custom_header()
    {
        IdempotencyKey::idempotencyHeader('X-Key');

        $key = Str::random();
        $request = $this->mock(Request::class, function (MockInterface $mock) use ($key) {
            $mock->shouldReceive('header')->with('X-Key')->once()->andReturn($key);
        });

        $this->assertEquals($key, (new IdempotencyKey)->handle($request));
    }

    #[Test]
    public function it_adds_relay_header_to_cached_response()
    {
        $key = Str::random();
        $response = $this->mock(Response::class, function (MockInterface $mock) use ($key) {
            $mock->shouldReceive('header')->with('Idempotency-Relayed', $key)->once();
        });

        $algorithm = invade(new IdempotencyKey);
        $algorithm->key = $key;
        $algorithm->handleCachedResponse($response);
    }
}
