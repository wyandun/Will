<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Expose Activation URL in API Response
    |--------------------------------------------------------------------------
    |
    | When true, the activation URL (containing the raw invitation token) is
    | included in the JSON response of store() and resend(). This is intended
    | exclusively for local development and testing environments where an email
    | service is not configured.
    |
    | NEVER set this to true in production or staging environments with real
    | user data. In production the URL travels ONLY via email to the invitee.
    |
    | Configured via: INVITATION_EXPOSE_URL=true in phpunit.xml / .env.local
    | Default: false (safe for production and staging)
    |
    */
    'expose_activation_url' => env('INVITATION_EXPOSE_URL', false),

    /*
    |--------------------------------------------------------------------------
    | Invitation Token TTL (days)
    |--------------------------------------------------------------------------
    |
    | Number of days until an invitation token expires. Stored in the database
    | as invitation_expires_at on the pending user record.
    |
    */
    'token_ttl_days' => env('INVITATION_TOKEN_TTL_DAYS', 7),
];
