<?php

namespace Tapp\LaravelHubspot\Models;

use HubSpot\Client\Crm\Associations\V4\ApiException as AssociationsApiException;
use HubSpot\Client\Crm\Associations\V4\Model\AssociationSpec;
use HubSpot\Client\Crm\Companies\Model\PublicObjectSearchRequest as CompanySearch;
use HubSpot\Client\Crm\Companies\Model\SimplePublicObjectInput as CompanyObject;
use HubSpot\Client\Crm\Contacts\ApiException;
use HubSpot\Client\Crm\Contacts\Model\Filter;
use HubSpot\Client\Crm\Contacts\Model\FilterGroup;
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

        // TODO this does not support using dot notation in map. need to compare values in api with DB
        // if ($model->isClean($model->hubspotMap)) {
        //     return;
        // }

        try {
            // TODO get by ID is unreliable (404 with api but works with web UI)
            // if ($model->hubspot_id) {
            // $hubspotCompany = Hubspot::crm()->companies()->basicApi()->getById($model->hubspot_id, null, null, null, false, 'id');
            // } else {
            $hubspotCompany = Hubspot::crm()->companies()->basicApi()->getById($model->email, null, null, null, false, 'email');

            $model->hubspot_id = $hubspotCompany['id'];
            // }
        } catch (ApiException $e) {
            // catch 404 error
            Log::debug('Hubspot company not found. Creating', ['email' => $model->email]);

            // return so we dont try to update afterwards
            return static::createHubspotCompany($model);
        }

        // outside of try block
        return static::updateHubspotCompany($model);
    }

    public static function createHubspotCompany($model)
    {
        try {
            $hubspotCompany = Hubspot::crm()->companies()->basicApi()->create($model->hubspotPropertiesObject());

            $model->hubspot_id = $hubspotCompany['id'];
        } catch (ApiException $e) {
            throw new \Exception('Error creating hubspot company: '.$e->getResponseBody());
            Log::error('Error creating hubspot company: '.$e->getResponseBody());

            return;
        }

        // $domain = preg_replace('/[^@]+@/i', '', $model->email);
        // $hubspotCompany = static::findOrCreateCompanyByDomain($domain);
        // static::associateCompanyWithCompany($hubspotCompany['id'], $hubspotCompany['id']);

        return $hubspotCompany;
    }

    public static function updateHubspotCompany($model)
    {
        if (! $model->hubspot_id) {
            throw new \Exception('Hubspot ID missing. Cannot update company: '.$model->email);
        }

        try {
            return Hubspot::crm()->companies()->basicApi()->update($model->hubspot_id, $model->hubspotPropertiesObject());
        } catch (ApiException $e) {
            Log::error('Hubspot company update failed', ['email' => $model->email]);
        }
    }

    /**
     * get properties to be synced with hubspot
     */
    public function hubspotProperties(): array
    {
        $properties = [];

        foreach ($this->hubspotMap as $key => $value) {
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
    public function hubspotPropertiesObject(): CompanyObject
    {
        return new CompanyObject(['properties' => $this->hubspotProperties()]);
    }

    /**
     * TODO untested
     */
    public static function findOrCreateCompanyByDomain(string $domain)
    {
        $filter = new Filter([
            'value' => $domain,
            'property_name' => 'domain',
            'operator' => 'EQ',
        ]);

        $filterGroup = new FilterGroup([
            'filters' => [$filter],
        ]);

        $companySearch = new CompanySearch([
            'filter_groups' => [$filterGroup],
        ]);

        $searchResults = Hubspot::crm()->companies()->searchApi()->doSearch($companySearch);

        $companyExists = $searchResults['total'];

        if ($companyExists) {
            return $searchResults['results'][0];
        } else {
            $properties = [
                'domain' => $domain,
            ];

            $companyObject = new CompanyObject([
                'properties' => $properties,
            ]);

            return Hubspot::crm()->companies()->basicApi()->create($companyObject);
        }
    }

    public static function associateCompanyWithCompany(string $companyId, string $companyId)
    {
        $associationSpec = new AssociationSpec([
            'association_category' => 'HUBSPOT_DEFINED',
            'association_type_id' => 1,
        ]);

        try {
            $apiResponse = Hubspot::crm()->associations()->v4()->basicApi()->create('company', $companyId, 'company', $companyId, [$associationSpec]);
        } catch (AssociationsApiException $e) {
            echo 'Exception when calling basic_api->create: ', $e->getMessage();
        }
    }
}
