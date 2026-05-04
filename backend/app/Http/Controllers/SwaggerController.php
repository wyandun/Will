<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'SM Portal API',
    version: '1.0.0',
    description: 'API del portal Strategic Mates'
)]
#[OA\Server(
    url: '/api/v1',
    description: 'Local / Railway'
)]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    description: 'Pegá el token que devuelve /auth/login'
)]
class SwaggerController {}
