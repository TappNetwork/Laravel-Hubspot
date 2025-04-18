<?php

namespace Tapp\LaravelHubSpot\Models;

use HubSpot\Client\Crm\Contacts\ApiException;
use HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput as ContactObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Tapp\LaravelHubSpot\Facades\HubSpot;

trait HubSpotContact
{
    // TODO put these in an interface
    // public array $hubspotMap = [];
    // public string $hubspotCompanyRelation = '';

    public static function bootHubSpotContact(): void
    {
        static::creating(fn (Model $model) => static::updateOrCreateHubSpotContact($model));

        static::updating(fn (Model $model) => static::updateOrCreateHubSpotContact($model));
    }

    public static function createHubSpotContact($model)
    {
        try {
            $hubSpotContact = HubSpot::crm()->contacts()->basicApi()->create($model->hubSpotPropertiesObject($model->hubSpotMap));

            $model->hubspot_id = $hubSpotContact['id'];

            return $hubSpotContact;
        } catch (ApiException $e) {
            Log::error('HubSpot contact creation failed', [
                'email' => $model->email,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $hubspotCompany = $model->getRelationValue($model->hubspotCompanyRelation);

        if ($hubspotCompany && ! $hubspotCompany->hubspot_id) {
            $hubspotCompany->touch();
            $hubspotCompany = $hubspotCompany->fresh();
        }

        if ($hubspotCompany && $hubspotCompany->hubspot_id) {
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
            Log::error('Hubspot contact update failed', [
                'email' => $model->email,
                'message' => $e->getMessage(),
                'response' => $e->getResponseBody(),
            ]);

            return;
        }

        $hubspotCompany = $model->getRelationValue($model->hubspotCompanyRelation);

        if ($hubspotCompany && ! $hubspotCompany->hubspot_id) {
            $hubspotCompany->touch();
            $hubspotCompany = $hubspotCompany->fresh();
        }

        if ($hubspotCompany && $hubspotCompany->hubspot_id) {
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
                Log::debug('Hubspot contact not found with id', [
                    'id' => $model->id,
                    'message' => $e->getMessage(),
                    'response' => $e->getResponseBody(),
                ]);
            }
        }

        // if no hubspot id or if id fetch failed, try fetching by email
        try {
            $hubspotContact = Hubspot::crm()->contacts()->basicApi()->getById($model->email, null, null, null, false, 'email');

            // dont save to prevent loop from model event
            $model->hubspot_id = $hubspotContact['id'];
        } catch (ApiException $e) {
            Log::debug('Hubspot contact not found with email', [
                'email' => $model->email,
                'message' => $e->getMessage(),
                'response' => $e->getResponseBody(),
            ]);
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

    public static function associateCompanyWithContact(string $companyId, string $contactId)
    {
        $associationSpec = new AssociationSpec([
            'association_category' => 'HUBSPOT_DEFINED',
            'association_type_id' => 1,
        ]);

        try {
            return Hubspot::crm()->associations()->v4()->basicApi()->create('contact', $contactId, 'company', $companyId, [$associationSpec]);
        } catch (AssociationsApiException $e) {
            // dd($companyId, $contactId);
            // dd($e);
            throw ($e);
        }
    }
}
