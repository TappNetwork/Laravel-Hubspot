<?php

namespace Tapp\LaravelHubspot\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Tapp\LaravelHubspot\LaravelHubspot
 */
class LaravelHubspot extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Tapp\LaravelHubspot\LaravelHubspot::class;
    }
}
