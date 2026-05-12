<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Per-page defaults
    |--------------------------------------------------------------------------
    |
    | Centralised page-size configuration for paginated API endpoints.
    | Each key maps to an env variable so it can be tuned per environment.
    |
    */

    'franchise_per_page' => (int) env('FRANCHISE_PER_PAGE', 25),

    'invitation_per_page' => (int) env('INVITATION_PER_PAGE', 25),
];
