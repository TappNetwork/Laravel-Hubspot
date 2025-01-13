<?php

namespace Tapp\LaravelHubspot\Models;

use HubSpot\Client\Crm\Associations\V4\ApiException as AssociationsApiException;
use HubSpot\Client\Crm\Associations\V4\Model\AssociationSpec;
use HubSpot\Client\Crm\Companies\Model\PublicObjectSearchRequest as CompanySearch;
use HubSpot\Client\Crm\Companies\Model\SimplePublicObjectInput as CompanyObject;
use HubSpot\Client\Crm\Companies\ApiException;
use HubSpot\Client\Crm\Companies\Model\Filter;
use HubSpot\Client\Crm\Companies\Model\FilterGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Tapp\LaravelHubspot\Facades\Hubspot;

trait HubspotCompany
{
    // public array $hubspotMap = [];

    public static function bootHubspotCompany(): void
    {
        static::creating(fn (Model $model) => static::updateOrCreateHubspotCompany($model));

        static::updating(fn (Model $model) => static::updateOrCreateHubspotCompany($model));
    }

    public static function createHubspotCompany($model)
    {
        try {
            $hubspotCompany = Hubspot::crm()->companies()->basicApi()->create($model->hubspotPropertiesObject($model->hubspotMap));

            $model->hubspot_id = $hubspotCompany['id'];
        } catch (ApiException $e) {
            throw new \Exception('Error creating hubspot company: '.$e->getResponseBody());
            Log::error('Error creating hubspot company: '.$e->getResponseBody());

            return;
        }

        return $hubspotCompany;
    }

    public static function updateHubspotCompany($model)
    {
        if (! $model->hubspot_id) {
            throw new \Exception('Hubspot ID missing. Cannot update company: '.$model->email);
        }

        try {
            Hubspot::crm()->companies()->basicApi()->update($model->hubspot_id, $model->hubspotPropertiesObject($model->hubspotMap));
        } catch (ApiException $e) {
            Log::error('Hubspot company update failed', ['email' => $model->email]);
        }

        return $hubspotCompany;
    }

    /*
     * if the model has a hubspot_id, find the company by id and update
     * if the model has an email, find the company by email and update
     * if the fetch requests fail, create a new company
     */
    public static function updateOrCreateHubspotCompany($model)
    {
        if (config('hubspot.disabled')) {
            return;
        }

        // TODO this does not support using dot notation in map
        // if ($model->isClean($model->hubspotMap)) {
        //     return;
        // }

        $hubspotCompany = static::getCompanyById($model);

        if (! $hubspotCompany) {
            return static::createHubspotCompany($model);
        }

        // outside of try block
        return static::updateHubspotCompany($model);
    }

    public static function getCompanyById($model)
    {
        $hubspotCompany = null;

        if ($model->hubspot_id) {
            try {
                return Hubspot::crm()->companies()->basicApi()->getById($model->hubspot_id);
            } catch (ApiException $e) {
                Log::debug('Hubspot company not found with id', ['id' => $model->id]);
            }
        }

        // TODO if no hubspot id or if id fetch failed, try searching by name
        // try {
        //     $hubspotCompany = Hubspot::crm()->companies()->basicApi()->getById($model->email, null, null, null, false, 'email');

        //     // dont save to prevent loop from model event
        //     $model->hubspot_id = $hubspotCompany['id'];
        // } catch (ApiException $e) {
        //     Log::debug('Hubspot company not found with email', ['email' => $model->email]);
        // }

        return $hubspotCompany;
    }

    /**
     * get properties to be synced with hubspot
     */
    public function hubspotProperties(array $map): array
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
    public function hubspotPropertiesObject(array $map): CompanyObject
    {
        return new CompanyObject(['properties' => $this->hubspotProperties($map)]);
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

        $companySearch = new CompanySearch([
            'filter_groups' => [$filterGroup],
        ]);

        try {
            $searchResults = Hubspot::crm()->companies()->searchApi()->doSearch($companySearch);
        } catch (\Exception $e) {
            // TODO debugging
            dump($filter, $properties);
            // dd($e);
            throw($e);
        }

        $companyExists = $searchResults['total'];

        if ($companyExists) {
            return $searchResults['results'][0];
        } else {
            $properties = [
                'na' => $domain,
            ];

            $companyObject = new CompanyObject([
                'properties' => $properties,
            ]);

            return Hubspot::crm()->companies()->basicApi()->create($companyObject);
        }
    }
}
