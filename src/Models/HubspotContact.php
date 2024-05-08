<?php

namespace Tapp\LaravelHubspot\Models;

use Tapp\LaravelHubspot\Facades\Hubspot;
use Illuminate\Database\Eloquent\Model;
use HubSpot\Client\Crm\Companies\Model\PublicObjectSearchRequest as CompanySearch;
use HubSpot\Client\Crm\Companies\Model\SimplePublicObjectInput as CompanyObject;
use HubSpot\Client\Crm\Contacts\Model\Filter;
use HubSpot\Client\Crm\Contacts\Model\FilterGroup;
use HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput as ContactObject;
use Illuminate\Support\Facades\Log;
use HubSpot\Client\Crm\Contacts\ApiException;

trait HubspotContact
{
    // public array $hubspotMap = [];

    public static function bootHubspotContact(): void
    {
        static::creating(fn (Model $model) =>
            static::updateOrCreateHubspotContact($model)
        );

        static::updating(fn (Model $model) =>
            static::updateOrCreateHubspotContact($model)
        );
    }

    public static function createHubspotContact($model): void
    {
        $hubspotContact = Hubspot::crm()->contacts()->basicApi()->create($model->hubspotPropertiesObject());

        $model->hubspot_id = $hubspotContact['id'];

        // TODO associate company from email domain with contact
        // $domain = preg_replace('/[^@]+@/i', '', $email);
        // $hubspotCompany = static::findOrCreateCompanyByDomain($domain);
        // $this->associateCompanyWithContact($hubspotCompany['id'], $hubspotContact['id']);
    }

    public static function updateHubspotContact($model): void
    {
        if (! $model->hubspot_id) {
            throw new \Exception('Hubspot ID missing. Cannot update contact: '. $model->email);
        }

        try {
            Hubspot::crm()->contacts()->basicApi()->update($model->hubspot_id, $model->hubspotPropertiesObject());
        } catch (ApiException $e) {
            Log::error('Hubspot contact update failed', ['email' => $model->email]);
        }
    }

    /*
     * if the model has a hubspot_id, find the contact by id and update
     * if the model has an email, find the contact by email and update
     * if the fetch requests fail, create a new contact
     */
    public static function updateOrCreateHubspotContact($model)
    {
        try {
            if ($model->hubspot_id) {
                $hubspotContact = Hubspot::crm()->contacts()->basicApi()->getById($model->id, null, null, null, false, 'email');
            } else {
                $hubspotContact = Hubspot::crm()->contacts()->basicApi()->getById($model->email, null, null, null, false, 'email');

                $model->hubspot_id = $hubspotContact['id'];
            }
        } catch (ApiException $e) {
            // catch 404 error
            Log::debug('Hubspot contact not found. Creating', ['email' => $model->email]);

            // return so we dont try to update afterwards
            return static::createHubspotContact($model);
        }

        // outside of try block
        return static::updateHubspotContact($model);
    }

    /**
     * get properties to be synced with hubspot
     */
    public function hubspotProperties(): Array
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
    public function hubspotPropertiesObject(): ContactObject
    {
        return new ContactObject(['properties' => $this->hubspotProperties()]);
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

    /**
     * TODO untested
     */
    public static function associateCompanyWithContact(string $companyId, string $contactId)
    {
        $apiResponse = Hubspot::crm()->contacts()->associationsApi()
            ->create(
                $contactId,
                'companies',
                $companyId,
                'contact_to_company'
            );
    }
}
