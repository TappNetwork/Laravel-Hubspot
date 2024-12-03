<?php

namespace Tapp\LaravelHubspot;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Utils;
use Psr\Http\Message\RequestInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Tapp\LaravelHubspot\Commands\SyncHubspotContacts;
use Tapp\LaravelHubspot\Commands\SyncHubspotProperties;

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
            ->hasMigration('add_hubspot_id_to_users_table')
            ->hasCommand(SyncHubspotProperties::class)
            ->hasCommand(SyncHubspotContacts::class);
    }

    public function bootingPackage()
    {
        $this->app->bind(LaravelHubspot::class, function ($app) {

            $stack = new HandlerStack;
            $stack->setHandler(Utils::chooseHandler());

            $stack->push(Middleware::mapRequest(function (RequestInterface $r) {
                if (config('hubspot.log_requests')) {
                    \Log::info('Hubspot Request: '.$r->getMethod().' '.$r->getUri());
                }

                return $r;
            }));

            $client = new Client(['handler' => $stack]);

            return LaravelHubspot::createWithAccessToken(config('hubspot.api_key'), $client);
        });
    }
}
