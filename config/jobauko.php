<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Consent versions
    |--------------------------------------------------------------------------
    |
    | Bump a version whenever the text of the privacy notice or the SMS consent
    | statement changes. Each recorded consent stores the version the user saw.
    |
    */

    'privacy_notice_version' => '2026-10-31',

    'sms_consent_version' => '2026-10-31',

    /*
    |--------------------------------------------------------------------------
    | Data protection contact
    |--------------------------------------------------------------------------
    |
    | Shown in the privacy notice as the contact for data privacy concerns
    | under the Data Privacy Act of 2012 (RA 10173).
    |
    */

    'privacy_contact_email' => env('PRIVACY_CONTACT_EMAIL', 'privacy@jobauko.test'),

    /*
    |--------------------------------------------------------------------------
    | Mobile number verification
    |--------------------------------------------------------------------------
    */

    'mobile_verification' => [
        'code_ttl_minutes' => 10,
        'max_attempts' => 5,
    ],

];
