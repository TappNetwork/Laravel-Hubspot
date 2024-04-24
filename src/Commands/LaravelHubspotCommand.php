<?php

namespace Tapp\LaravelHubspot\Commands;

use Illuminate\Console\Command;

class LaravelHubspotCommand extends Command
{
    public $signature = 'laravel-hubspot';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
