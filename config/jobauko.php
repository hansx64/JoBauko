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

    /*
    |--------------------------------------------------------------------------
    | Reverse proxy
    |--------------------------------------------------------------------------
    |
    | Behind a load balancer or proxy that terminates HTTPS, list its IP
    | addresses (comma separated, or "*" for any) so request IPs in the audit
    | log and the HTTPS check are correct.
    |
    */

    'trusted_proxies' => env('TRUSTED_PROXIES'),

    /*
    |--------------------------------------------------------------------------
    | Overall request limit
    |--------------------------------------------------------------------------
    |
    | Requests per minute for each signed-in user, or each IP address for
    | visitors, across the whole site. Sign-in, registration, codes, uploads,
    | applications, reports and exports have stricter limits of their own.
    |
    */

    'requests_per_minute' => (int) env('RATE_LIMIT_PER_MINUTE', 120),

];
