<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

/**
 * Shared writable fields for Company store and update requests.
 * Used by CompanyController::store(), update(), and closeDeal().
 */
#[OA\Schema(
    schema: 'CompanyWriteInput',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Tacos El Gordo LLC'),
        new OA\Property(property: 'sm_franchise_id', type: 'integer', example: 1),
        new OA\Property(property: 'industry', type: 'string', nullable: true, maxLength: 255, example: 'Food & Beverage'),
        new OA\Property(property: 'address', type: 'string', nullable: true, maxLength: 255, example: '789 Biscayne Blvd'),
        new OA\Property(property: 'city', type: 'string', nullable: true, maxLength: 255, example: 'Miami'),
        new OA\Property(property: 'state', type: 'string', nullable: true, maxLength: 50, example: 'FL'),
        new OA\Property(property: 'country', type: 'string', nullable: true, maxLength: 50, example: 'USA'),
        new OA\Property(property: 'phone', type: 'string', nullable: true, maxLength: 30, example: '+13055559999'),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, maxLength: 255, example: 'contact@tacosgordo.com'),
        new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true, maxLength: 255, example: 'https://tacosgordo.com'),
        new OA\Property(property: 'logo_path', type: 'string', nullable: true, maxLength: 255, example: null),
        new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Cliente referido por SM Florida.'),
    ]
)]
class CompanySchemas {}
