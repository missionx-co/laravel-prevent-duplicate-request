<?php

namespace MissionX\LaravelPreventDuplicateRequest\Tests\Unit\UniqueKeyAlgorithms;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use MissionX\LaravelPreventDuplicateRequest\Tests\TestCase;
use MissionX\LaravelPreventDuplicateRequest\UniqueKeyAlgorithms\RequestFingerprintGenerator;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class RequestFingerprintGeneratorTest extends TestCase
{
    #[Test]
    public function it_generates_fingerprint()
    {
        $url = fake()->url();
        $input = ['key' => 'value'];
        $file = UploadedFile::fake()->create('file.csv');

        $req = $this->mock(Request::class, function (MockInterface $mock) use ($url, $input, $file) {
            $mock->shouldReceive('input')->once()->andReturn($input);
            $mock->shouldReceive('fullUrl')->once()->andReturn($url);
            $mock->shouldReceive('allFiles')->once()->andReturn([
                'file' => $file,
            ]);
        });

        $hash = hash(
            'sha256',
            serialize([
                'key' => 'value',
                'file' => 'file.csv0',
                'url' => $url,
            ])
        );

        $this->assertEquals($hash, (new RequestFingerprintGenerator)->handle($req));
    }
}
