<?php

// config for Tapp/LaravelHubspot
return [
    'api_key' => env('HUBSPOT_TOKEN'),
    'log_requests' => env('HUBSPOT_LOG_REQUESTS', false),
    'property_group' => env('HUBSPOT_PROPERTY_GROUP', 'app_user_profile'),
    'property_group_label' => env('HUBSPOT_PROPERTY_GROUP_LABEL', 'App User Profile'),
];
