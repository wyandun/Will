<?php

namespace App\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'BbAssignmentResource',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'bb_user_id', type: 'integer', example: 8),
        new OA\Property(property: 'company_id', type: 'integer', example: 3),
        new OA\Property(property: 'assigned_by', type: 'integer', example: 1),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ]
)]
class BbAssignmentSchema {}
