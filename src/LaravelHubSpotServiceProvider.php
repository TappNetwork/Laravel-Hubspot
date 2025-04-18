<?php

namespace Tapp\LaravelHubSpot;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Utils;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tapp\LaravelHubSpot\Commands\SyncHubSpotContacts;
use Tapp\LaravelHubSpot\Commands\SyncHubSpotProperties;

class LaravelHubSpotServiceProvider extends PackageServiceProvider
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
            ->hasMigration('add_hubspot_id_to_users_table')
            ->hasCommand(SyncHubspotProperties::class)
            ->hasCommand(SyncHubspotContacts::class);
    }

    public function packageRegistered()
    {
        $this->app->bind(LaravelHubSpot::class, function ($app) {
            $stack = HandlerStack::create();

            $stack->push(
                Middleware::mapRequest(function (RequestInterface $r) {
                    \Log::info('HubSpot Request: '.$r->getMethod().' '.$r->getUri());
                    return $r;
                })
            );

            $client = new Client(['handler' => $stack]);

            return LaravelHubSpot::createWithAccessToken(config('hubspot.api_key'), $client);
        });
    }
}
