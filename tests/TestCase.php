<?php

namespace MissionX\LaravelPreventDuplicateRequest\Tests;

use MissionX\LaravelPreventDuplicateRequest\LaravelPreventDuplicateRequestServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            LaravelPreventDuplicateRequestServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
