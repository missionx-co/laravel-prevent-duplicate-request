<?php

namespace MissionX\LaravelPreventDuplicateRequest;

use MissionX\LaravelPreventDuplicateRequest\Middlewares\PreventDuplicateRequestMiddleware;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelPreventDuplicateRequestServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-prevent-duplicate-request')
            ->hasConfigFile();
    }

    public function packageRegistered()
    {
        $this->app['router']->aliasMiddleware('preventDuplicateRequests', PreventDuplicateRequestMiddleware::class);
        $this->app->singleton(PreventDuplicateRequestMiddleware::class);
    }
}
