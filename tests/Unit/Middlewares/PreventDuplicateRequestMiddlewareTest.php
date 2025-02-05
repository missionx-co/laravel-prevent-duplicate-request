<?php

namespace MissionX\LaravelPreventDuplicateRequest\Tests\Unit\Middlewares;

use Illuminate\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use MissionX\LaravelPreventDuplicateRequest\Exceptions\MissingIdempotencyKeyException;
use MissionX\LaravelPreventDuplicateRequest\Middlewares\PreventDuplicateRequestMiddleware;
use MissionX\LaravelPreventDuplicateRequest\Tests\TestCase;
use MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms\IdempotencyKey;
use MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms\RequestFingerprintGenerator;
use MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms\UniqueKeyAlgorithm;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class PreventDuplicateRequestMiddlewareTest extends TestCase
{
    #[Test]
    #[DataProvider('algorithmDataProvider')]
    public function it_creates_algorithm_based_on_config($algorithm)
    {
        config(['prevent-duplicate-request.key_algorithm' => $algorithm]);

        $this->assertInstanceOf($algorithm, invade($this->middleware())->algorithm());
    }

    #[Test]
    public function it_resolves_user_from_current_authenticated_user()
    {
        $id = Str::random();
        $user = $this->mock(Authenticatable::class, function (MockInterface $mock) use ($id) {
            $mock->shouldReceive('getKey')->once()->andReturn($id);
        });

        $request = $this->mock(Request::class, function (MockInterface $mock) use ($user) {
            $mock->shouldReceive('user')->twice()->andReturn($user);
            $mock->shouldReceive('ip')->never();
        });

        $this->assertEquals($id, invade($this->middleware())->resolveUserId($request));
    }

    #[Test]
    public function it_resolves_user_from_ip()
    {
        $ip = fake()->ipv4();
        $request = $this->mock(Request::class, function (MockInterface $mock) use ($ip) {
            $mock->shouldReceive('user')->once()->andReturnNull();
            $mock->shouldReceive('ip')->once()->andReturn($ip);
        });

        $this->assertEquals($ip, invade($this->middleware())->resolveUserId($request));
    }

    #[Test]
    public function it_resolves_user_using_custom_resolver()
    {
        $id = Str::random();
        PreventDuplicateRequestMiddleware::removeUserIdUsing(fn () => $id);
        $request = $this->mock(Request::class, function (MockInterface $mock) {
            $mock->shouldReceive('user')->never();
            $mock->shouldReceive('ip')->never();
        });

        $this->assertEquals($id, invade($this->middleware())->resolveUserId($request));
    }

    public static function algorithmDataProvider()
    {
        return [
            ['algorithm' => IdempotencyKey::class],
            ['algorithm' => RequestFingerprintGenerator::class],
        ];
    }

    #[Test]
    public function it_generates_key()
    {
        $key = Str::random();
        $userId = Str::random();
        config(['prevent-duplicate-request.cache_prefix' => 'prefix']);
        $algorithm = $this->mock(UniqueKeyAlgorithm::class, function (MockInterface $mock) use ($key) {
            $mock->shouldReceive('handle')->once()->andReturn($key);
        });
        $this->middleware()->shouldReceive('algorithm')->once()->andReturn($algorithm);
        $this->middleware()->shouldReceive('resolveUserId')->once()->andReturn($userId);

        $this->assertEquals(
            "prefix_{$userId}_{$key}",
            invade($this->middleware())->generateKey($this->mock(Request::class))
        );
    }

    #[Test]
    public function it_throws_exception_if_key_was_not_found()
    {
        config(['prevent-duplicate-request.cache_prefix' => 'prefix']);
        $algorithm = $this->mock(UniqueKeyAlgorithm::class, function (MockInterface $mock) {
            $mock->shouldReceive('handle')->once()->andReturn('');
        });
        $this->middleware()->shouldReceive('algorithm')->once()->andReturn($algorithm);

        $this->expectException(MissingIdempotencyKeyException::class);
        invade($this->middleware())->generateKey($this->mock(Request::class));
    }

    #[Test]
    public function it_saves_and_rebuilds_response_in_cache()
    {
        $key = Str::random();
        $path = fake()->word();
        $request = $this->mock(Request::class, function (MockInterface $mock) use ($path) {
            $mock->shouldReceive('path')->twice()->andReturn($path);
        });

        $content = 'content';
        $statusCode = 200;
        $response = $this->mock(Response::class, function (MockInterface $mock) use ($content, $statusCode) {
            $mock->shouldReceive('getContent')->once()->andReturn($content);
            $mock->shouldReceive('getStatusCode')->once()->andReturn($statusCode);
        });
        $response->headers = new ResponseHeaderBag(['Key' => 'Value']);

        invade($this->middleware())->saveResponseInCache($key, $request, $response);

        $this->assertEquals(
            [
                'path' => $path,
                'body' => $content,
                'status' => $statusCode,
                'headers' => $response->headers->all(),
            ],
            $responseInCache = invade($this->middleware())->store()->get($key)
        );

        $algorithm = $this->mock(UniqueKeyAlgorithm::class, function (MockInterface $mock) {
            $mock->shouldReceive('handleCachedResponse')->once();
        });
        $this->middleware()->shouldReceive('algorithm')->once()->andReturn($algorithm);

        // rebuilding
        $res = invade($this->middleware())->buildResponseFromCache($request, $responseInCache);

        $this->assertEquals($statusCode, $res->getStatusCode());
        $this->assertEquals($res->getContent(), $content);
        $this->assertEquals($res->headers->all(), $response->headers->all());
    }

    #[Test]
    public function it_does_not_cache_if_method_is_safe_method()
    {
        $request = $this->mock(Request::class, function (MockInterface $mock) {
            $mock->shouldReceive('isMethodSafe')->once()->andReturn(true);
        });

        $this->middleware()->shouldReceive('generateKey')->never();
        $this->middleware()->shouldReceive('buildResponseFromCache')->never();
        $this->middleware()->shouldReceive('saveResponseInCache')->never();

        $response = $this->mock(Response::class);
        $this->assertSame($response, $this->middleware()->handle($request, fn () => $response));
    }

    #[Test]
    public function it_saves_reponse_in_cache()
    {
        $request = $this->mock(Request::class, function (MockInterface $mock) {
            $mock->shouldReceive('isMethodSafe')->once()->andReturn(false);
        });

        $key = Str::random();
        $response = $this->mock(Response::class);

        $this->middleware()->shouldReceive('generateKey')->once()->andReturn($key);
        $this->middleware()->shouldReceive('buildResponseFromCache')->never();
        $this->middleware()->shouldReceive('saveResponseInCache')->once()
            ->withArgs(
                fn ($expectedKey, $expectedRequest, $expectedResponse) => $expectedKey == $key &&
                    $expectedRequest == $request &&
                    $expectedResponse == $response
            );

        $this->assertSame($response, $this->middleware()->handle($request, fn () => $response));
    }

    #[Test]
    public function it_builds_response_from_cache()
    {
        $request = $this->mock(Request::class, function (MockInterface $mock) {
            $mock->shouldReceive('isMethodSafe')->once()->andReturn(false);
        });

        $key = Str::random();
        $response = $this->mock(Response::class);

        invade($this->middleware())->store()->put($key, ['response']);

        $this->middleware()->shouldReceive('generateKey')->once()->andReturn($key);
        $this->middleware()->shouldReceive('buildResponseFromCache')->once()->andReturn($response);
        $this->middleware()->shouldReceive('saveResponseInCache')->never();

        $this->assertSame($response, $this->middleware()->handle($request, fn () => $this->fail('Response should not be processed')));
    }

    public function middleware(): PreventDuplicateRequestMiddleware|MockInterface
    {
        return once(
            fn () => $this->mock(PreventDuplicateRequestMiddleware::class, function (MockInterface $mock) {
                $mock->makePartial();
                $mock->shouldAllowMockingProtectedMethods();
            })
        );
    }
}
