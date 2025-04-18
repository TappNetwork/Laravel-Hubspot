<?php

namespace Tapp\LaravelHubSpot\Models;

use HubSpot\Client\Crm\Companies\ApiException;
use HubSpot\Client\Crm\Companies\Model\Filter;
use HubSpot\Client\Crm\Companies\Model\FilterGroup;
use HubSpot\Client\Crm\Companies\Model\PublicObjectSearchRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Tapp\LaravelHubSpot\Facades\HubSpot;

trait HubSpotCompany
{
    // public array $hubspotMap = [];

    public static function bootHubSpotCompany(): void
    {
        static::creating(fn (Model $model) => static::updateOrCreateHubSpotCompany($model));

        static::updating(fn (Model $model) => static::updateOrCreateHubSpotCompany($model));
    }

    public static function createHubSpotCompany($model)
    {
        try {
            $hubSpotCompany = HubSpot::crm()->companies()->basicApi()->create($model->hubSpotPropertiesObject($model->hubSpotMap));

            $model->hubspot_id = $hubSpotCompany['id'];

            return $hubSpotCompany;
        } catch (ApiException $e) {
            Log::error('HubSpot company creation failed', [
                'email' => $model->email,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public static function updateHubSpotCompany($model)
    {
        if (! $model->hubspot_id) {
            throw new \Exception('HubSpot ID missing. Cannot update company: '.$model->email);
        }

        try {
            return HubSpot::crm()->companies()->basicApi()->update($model->hubspot_id, $model->hubSpotPropertiesObject($model->hubSpotMap));
        } catch (ApiException $e) {
            Log::error('HubSpot company update failed', ['email' => $model->email]);

            return null;
        }
    }

    /*
     * if the model has a hubspot_id, find the company by id and update
     * if the model has an email, find the company by email and update
     * if the fetch requests fail, create a new company
     */
    public static function updateOrCreateHubSpotCompany($model)
    {
        if (! isset($model->hubSpotMap)) {
            return null;
        }

        try {
            $company = static::getHubSpotCompany($model);

            if ($company) {
                $model->hubspot_id = $company['id'];

                return static::updateHubSpotCompany($model);
            }

            return static::createHubSpotCompany($model);
        } catch (ApiException $e) {
            Log::error('HubSpot company update or create failed', [
                'email' => $model->email,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public static function getHubSpotCompany($model)
    {
        if ($model->hubspot_id) {
            try {
                return HubSpot::crm()->companies()->basicApi()->getById($model->hubspot_id);
            } catch (ApiException $e) {
                Log::debug('HubSpot company not found with id', [
                    'id' => $model->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $filter = new Filter([
            'propertyName' => 'domain',
            'operator' => 'EQ',
            'value' => $model->domain,
        ]);

        $filterGroup = new FilterGroup(['filters' => [$filter]]);
        $searchRequest = new PublicObjectSearchRequest(['filterGroups' => [$filterGroup]]);

        try {
            $searchResults = HubSpot::crm()->companies()->searchApi()->doSearch($searchRequest);

            if (count($searchResults['results']) > 0) {
                return $searchResults['results'][0];
            }
        } catch (ApiException $e) {
            Log::debug('HubSpot company search failed', [
                'domain' => $model->domain,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * get properties to be synced with hubspot
     */
    public function hubSpotProperties(array $map): array
    {
        $properties = [];

        foreach ($map as $key => $value) {
            if (strpos($value, '.')) {
                $properties[$key] = data_get($this, $value);
            } else {
                $properties[$key] = $this->$value;
            }
        }

        return $properties;
    }

    /**
     * get properties to be synced with hubspot
     */
    public function hubSpotPropertiesObject(array $map): array
    {
        $properties = [];

        foreach ($map as $hubSpotProperty => $modelProperty) {
            if (is_callable($modelProperty)) {
                $properties[$hubSpotProperty] = $modelProperty($this);
            } else {
                $properties[$hubSpotProperty] = $this->{$modelProperty};
            }
        }

        return ['properties' => $properties];
    }

    public static function findOrCreateCompany($properties)
    {
        $filter = new Filter([
            'value' => $properties['name'],
            'property_name' => 'name',
            'operator' => 'EQ',
        ]);

        $filterGroup = new FilterGroup([
            'filters' => [$filter],
        ]);

        $companySearch = new PublicObjectSearchRequest([
            'filter_groups' => [$filterGroup],
        ]);

        try {
            $searchResults = HubSpot::crm()->companies()->searchApi()->doSearch($companySearch);
        } catch (\Exception $e) {
            // dump($filter, $properties);
            // dd($e);
            throw ($e);
        }

        $companyExists = $searchResults['total'];

        if ($companyExists) {
            return $searchResults['results'][0];
        } else {
            $properties = [
                'na' => $domain,
            ];

            $companyObject = new PublicObjectSearchRequest([
                'properties' => $properties,
            ]);

            return HubSpot::crm()->companies()->basicApi()->create($companyObject);
        }
    }
}
