<?php

namespace Tapp\LaravelHubSpot\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use HubSpot\Client\Crm\Properties\Model\PropertyCreate;
use HubSpot\Client\Crm\Properties\Model\PropertyGroupCreate;
use Tapp\LaravelHubSpot\Facades\HubSpot;

class SyncHubSpotProperties extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    public $signature = 'hubspot:sync-properties';

    /**
     * The console command description.
     *
     * @var string
     */
    public $description = 'Sync properties with HubSpot';

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
        $this->syncProperties();

        return self::SUCCESS;
    }

    protected function syncProperties()
    {
        $object = 'contacts';

        try {
            $response = HubSpot::crm()->properties()->coreApi()->getAll($object, false);

            $existingProperties = collect($response->getResults())->pluck('name')->toArray();

            $propertyGroup = $this->createPropertyGroup($object);

            $properties = config('hubspot.properties');

            foreach ($properties as $property) {
                if (! in_array($property['name'], $existingProperties)) {
                    $this->createProperty($object, $property, $propertyGroup);
                }
            }
        } catch (\Exception $e) {
            Log::error('Error syncing HubSpot properties', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function createProperty($object, $property, $propertyGroup)
    {
        try {
            $data = [
                new PropertyCreate([
                    'name' => $property['name'],
                    'label' => $property['label'],
                    'type' => $property['type'],
                    'field_type' => $property['field_type'],
                    'group_name' => $propertyGroup->getName(),
                ]),
            ];

            $response = HubSpot::crm()->properties()->batchApi()->create($object, $data);

            $this->info("Created property: {$property['name']}");

            return $response;
        } catch (\Exception $e) {
            Log::error('Error creating HubSpot property', [
                'property' => $property['name'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function createPropertyGroup($object)
    {
        try {
            $propertyGroupCreate = new PropertyGroupCreate([
                'name' => config('hubspot.property_group'),
                'label' => config('hubspot.property_group_label'),
            ]);

            return HubSpot::crm()->properties()->groupsApi()->create($object, $propertyGroupCreate);
        } catch (\Exception $e) {
            Log::error('Error creating HubSpot property group', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
