<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Response(
    response: 'Unauthenticated',
    description: 'No autenticado'
)]
#[OA\Response(
    response: 'Forbidden',
    description: 'Sin permiso'
)]
#[OA\Response(
    response: 'NotFound',
    description: 'Recurso no encontrado'
)]
class Responses {}
