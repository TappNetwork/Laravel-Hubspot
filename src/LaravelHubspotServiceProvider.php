<?php

namespace Tapp\LaravelHubspot;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tapp\LaravelHubspot\Commands\LaravelHubspotCommand;

class LaravelHubspotServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-hubspot')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_laravel-hubspot_table')
            ->hasCommand(LaravelHubspotCommand::class);
    }
}
