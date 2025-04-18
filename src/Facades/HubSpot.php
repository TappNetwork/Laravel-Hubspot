<?php

namespace Tapp\LaravelHubSpot\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Tapp\LaravelHubSpot\LaravelHubSpot
 */
class HubSpot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Tapp\LaravelHubSpot\LaravelHubSpot::class;
    }
}
