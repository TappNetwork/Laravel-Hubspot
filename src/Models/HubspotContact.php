<?php

namespace Tapp\LaravelHubspot\Models;

use HubSpot\Client\Crm\Associations\V4\ApiException as AssociationsApiException;
use HubSpot\Client\Crm\Associations\V4\Model\AssociationSpec;
use HubSpot\Client\Crm\Companies\Model\PublicObjectSearchRequest as CompanySearch;
use HubSpot\Client\Crm\Companies\Model\SimplePublicObjectInput as CompanyObject;
use HubSpot\Client\Crm\Contacts\ApiException;
use HubSpot\Client\Crm\Contacts\Model\Filter;
use HubSpot\Client\Crm\Contacts\Model\FilterGroup;
use HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput as ContactObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Tapp\LaravelHubspot\Facades\Hubspot;

trait HubspotContact
{
    // TODO put these in an interface
    // public array $hubspotMap = [];
    // public string $hubspotCompanyRelation = '';

    public static function bootHubspotContact(): void
    {
        static::creating(fn (Model $model) => static::updateOrCreateHubspotContact($model));

        static::updating(fn (Model $model) => static::updateOrCreateHubspotContact($model));
    }

    public static function createHubspotContact($model)
    {
        try {
            $hubspotContact = Hubspot::crm()->contacts()->basicApi()->create($model->hubspotPropertiesObject($model->hubspotMap));

            $model->hubspot_id = $hubspotContact['id'];
        } catch (ApiException $e) {
            throw new \Exception('Error creating hubspot contact: '.$e->getResponseBody());
            Log::error('Error creating hubspot contact: '.$e->getResponseBody());

            return;
        }

        $hubspotCompany = $model->getRelationValue($model->hubspotCompanyRelation);

        if ($hubspotCompany) {
            static::associateCompanyWithContact($hubspotCompany->hubspot_id, $hubspotContact['id']);
        }

        return $hubspotContact;
    }

    public static function updateHubspotContact($model)
    {
        if (! $model->hubspot_id) {
            throw new \Exception('Hubspot ID missing. Cannot update contact: '.$model->email);
        }

        try {
            $hubspotContact = Hubspot::crm()->contacts()->basicApi()->update($model->hubspot_id, $model->hubspotPropertiesObject($model->hubspotMap));
        } catch (ApiException $e) {
            Log::error('Hubspot contact update failed', ['email' => $model->email]);
        }

        $hubspotCompany = $model->getRelationValue($model->hubspotCompanyRelation);

        if ($hubspotCompany) {
            static::associateCompanyWithContact($hubspotCompany->hubspot_id, $hubspotContact['id']);
        }

        return $hubspotContact;
    }

    /*
     * if the model has a hubspot_id, find the contact by id and update
     * if the model has an email, find the contact by email and update
     * if the fetch requests fail, create a new contact
     */
    public static function updateOrCreateHubspotContact($model)
    {
        if (config('hubspot.disabled')) {
            return;
        }

        // TODO this does not support using dot notation in map
        // if ($model->isClean($model->hubspotMap)) {
        //     return;
        // }

        $hubspotContact = static::getContactByEmailOrId($model);

        if (! $hubspotContact) {
            return static::createHubspotContact($model);
        }

        // outside of try block
        return static::updateHubspotContact($model);
    }

    public static function getContactByEmailOrId($model)
    {
        $hubspotContact = null;

        if ($model->hubspot_id) {
            try {
                return Hubspot::crm()->contacts()->basicApi()->getById($model->hubspot_id);
            } catch (ApiException $e) {
                Log::debug('Hubspot contact not found with id', ['id' => $model->id]);
            }
        }

        // if no hubspot id or if id fetch failed, try fetching by email
        try {
            $hubspotContact = Hubspot::crm()->contacts()->basicApi()->getById($model->email, null, null, null, false, 'email');

            // dont save to prevent loop from model event
            $model->hubspot_id = $hubspotContact['id'];
        } catch (ApiException $e) {
            Log::debug('Hubspot contact not found with email', ['email' => $model->email]);
        }

        return $hubspotContact;
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
    public function hubspotPropertiesObject(array $map): ContactObject
    {
        return new ContactObject(['properties' => $this->hubspotProperties($map)]);
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

        $searchResults = Hubspot::crm()->companies()->searchApi()->doSearch($companySearch);

        $companyExists = $searchResults['total'];

        if ($companyExists) {
            return $searchResults['results'][0];
        } else {
            // TODO create company
            dd('todo create company from relation');
            $properties = [
                'na' => $domain,
            ];

            $companyObject = new CompanyObject([
                'properties' => $properties,
            ]);

            return Hubspot::crm()->companies()->basicApi()->create($companyObject);
        }
    }

    public static function associateCompanyWithContact(string $companyId, string $contactId)
    {
        $associationSpec = new AssociationSpec([
            'association_category' => 'HUBSPOT_DEFINED',
            'association_type_id' => 1,
        ]);

        try {
            $apiResponse = Hubspot::crm()->associations()->v4()->basicApi()->create('contact', $contactId, 'company', $companyId, [$associationSpec]);
        } catch (AssociationsApiException $e) {
            // dd($companyId, $contactId);
            dd($e);
            throw($e);
            echo 'Exception when calling basic_api->create: ', $e->getMessage();
        }
    }
}
