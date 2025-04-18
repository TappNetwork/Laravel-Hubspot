<?php

namespace Tapp\LaravelHubSpot\Commands;

use Illuminate\Console\Command;
use Tapp\LaravelHubSpot\Models\HubSpotContact;

class SyncHubSpotContacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hubspot:sync-contacts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync contacts with HubSpot';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $contacts = HubSpotContact::all();

        foreach ($contacts as $contact) {
            HubSpotContact::updateOrCreateHubSpotContact($contact);
        }

        return self::SUCCESS;
    }
}
