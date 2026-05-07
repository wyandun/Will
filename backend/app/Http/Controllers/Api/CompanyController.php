<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class CompanyController extends Controller
{
    public function __construct(private CompanyService $companyService) {}

    #[OA\Get(
        path: '/companies',
        tags: ['Companies'],
        summary: 'Listar empresas visibles para el usuario autenticado',
        description: 'Superadmin ve todas las empresas. admin_sm ve las de su franquicia. sb_owner y sb_employee ven solo su empresa.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Filtrar por nombre, industria o ciudad',
                schema: new OA\Schema(type: 'string', example: 'Tacos')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de empresas',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Tacos El Gordo LLC'),
                                    new OA\Property(property: 'industry', type: 'string', nullable: true, example: 'Food & Beverage'),
                                    new OA\Property(property: 'address', type: 'string', nullable: true, example: '789 Biscayne Blvd'),
                                    new OA\Property(property: 'city', type: 'string', nullable: true, example: 'Miami'),
                                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+13055559999'),
                                    new OA\Property(property: 'email', type: 'string', nullable: true, example: 'contact@tacosgordo.com'),
                                    new OA\Property(property: 'website', type: 'string', nullable: true, example: 'https://tacosgordo.com'),
                                    new OA\Property(property: 'state', type: 'string', nullable: true, example: 'FL'),
                                    new OA\Property(property: 'country', type: 'string', nullable: true, example: 'USA'),
                                    new OA\Property(property: 'logo_path', type: 'string', nullable: true, example: null),
                                    new OA\Property(property: 'employees_count', type: 'integer', nullable: true, example: 12),
                                    new OA\Property(property: 'annual_revenue', type: 'number', format: 'float', nullable: true, example: 450000.00),
                                    new OA\Property(property: 'years_operating', type: 'integer', nullable: true, example: 4),
                                    new OA\Property(property: 'sm_franchise_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'franchise_name', type: 'string', nullable: true, example: 'SM Florida'),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permiso para listar empresas'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Company::class);

        $companies = $this->companyService->list($request->user());

        return CompanyResource::collection($companies);
    }

    #[OA\Post(
        path: '/companies',
        tags: ['Companies'],
        summary: 'Crear una empresa (ruta interna directa)',
        description: 'Crea solo el registro de empresa, sin los mapas de proceso. Para el flujo completo de onboarding usar POST /companies/close-deal.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'sm_franchise_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Tacos El Gordo LLC'),
                    new OA\Property(property: 'sm_franchise_id', type: 'integer', example: 1),
                    new OA\Property(property: 'industry', type: 'string', nullable: true, maxLength: 255, example: 'Food & Beverage'),
                    new OA\Property(property: 'address', type: 'string', nullable: true, maxLength: 255, example: '789 Biscayne Blvd'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, maxLength: 30, example: '+13055559999'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, maxLength: 255, example: 'contact@tacosgordo.com'),
                    new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true, maxLength: 255, example: 'https://tacosgordo.com'),
                    new OA\Property(property: 'city', type: 'string', nullable: true, maxLength: 255, example: 'Miami'),
                    new OA\Property(property: 'state', type: 'string', nullable: true, maxLength: 50, example: 'FL'),
                    new OA\Property(property: 'country', type: 'string', nullable: true, maxLength: 50, example: 'USA'),
                    new OA\Property(property: 'logo_path', type: 'string', nullable: true, maxLength: 255, example: null),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Cliente referido por SM Florida.'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Empresa creada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'message', type: 'string', example: 'Empresa creada correctamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permiso para crear empresas'),
            new OA\Response(response: 422, description: 'Error de validación'),
        ]
    )]
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $company = $this->companyService->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
            'message' => 'Empresa creada correctamente.',
        ], 201);
    }

    #[OA\Get(
        path: '/companies/{id}',
        tags: ['Companies'],
        summary: 'Obtener una empresa por ID',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la empresa',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Datos de la empresa (incluye franchise_name)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'message', type: 'string', example: 'OK.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permiso para ver esta empresa'),
            new OA\Response(response: 404, description: 'Empresa no encontrada'),
        ]
    )]
    public function show(Company $company): JsonResponse
    {
        $this->authorize('view', $company);

        $company->loadMissing('franchise');

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
            'message' => 'OK.',
        ]);
    }

    #[OA\Put(
        path: '/companies/{id}',
        tags: ['Companies'],
        summary: 'Actualizar una empresa existente',
        description: 'Todos los campos son opcionales (semántica PATCH).',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la empresa',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Tacos El Gordo LLC'),
                    new OA\Property(property: 'sm_franchise_id', type: 'integer', example: 1),
                    new OA\Property(property: 'industry', type: 'string', nullable: true, maxLength: 255, example: 'Food & Beverage'),
                    new OA\Property(property: 'address', type: 'string', nullable: true, maxLength: 255, example: '789 Biscayne Blvd'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, maxLength: 30, example: '+13055559999'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, maxLength: 255, example: 'contact@tacosgordo.com'),
                    new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true, maxLength: 255, example: 'https://tacosgordo.com'),
                    new OA\Property(property: 'city', type: 'string', nullable: true, maxLength: 255, example: 'Miami'),
                    new OA\Property(property: 'state', type: 'string', nullable: true, maxLength: 50, example: 'FL'),
                    new OA\Property(property: 'country', type: 'string', nullable: true, maxLength: 50, example: 'USA'),
                    new OA\Property(property: 'logo_path', type: 'string', nullable: true, maxLength: 255, example: null),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: null),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Empresa actualizada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'message', type: 'string', example: 'Empresa actualizada correctamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permiso para actualizar esta empresa'),
            new OA\Response(response: 404, description: 'Empresa no encontrada'),
            new OA\Response(response: 422, description: 'Error de validación'),
        ]
    )]
    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $this->authorize('update', $company);

        $company = $this->companyService->update($company, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
            'message' => 'Empresa actualizada correctamente.',
        ]);
    }

    #[OA\Delete(
        path: '/companies/{id}',
        tags: ['Companies'],
        summary: 'Eliminar una empresa',
        description: 'Solo superadmin puede eliminar empresas.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la empresa',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Empresa eliminada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'message', type: 'string', example: 'Empresa eliminada correctamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permiso para eliminar esta empresa'),
            new OA\Response(response: 404, description: 'Empresa no encontrada'),
        ]
    )]
    public function destroy(Company $company): JsonResponse
    {
        $this->authorize('delete', $company);

        $this->companyService->delete($company);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Empresa eliminada correctamente.',
        ]);
    }

    #[OA\Post(
        path: '/companies/close-deal',
        tags: ['Companies'],
        summary: 'Cerrar deal — crear empresa con mapas de proceso',
        description: 'Flujo canónico de onboarding. Crea la empresa y sus dos mapas de proceso obligatorios (franquiciadora y franquiciada) en una sola transacción de BD. Usar este endpoint en lugar de POST /companies para el flujo del frontend.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'sm_franchise_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Tacos El Gordo LLC'),
                    new OA\Property(property: 'sm_franchise_id', type: 'integer', example: 1),
                    new OA\Property(property: 'industry', type: 'string', nullable: true, maxLength: 255, example: 'Food & Beverage'),
                    new OA\Property(property: 'address', type: 'string', nullable: true, maxLength: 255, example: '789 Biscayne Blvd'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, maxLength: 30, example: '+13055559999'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, maxLength: 255, example: 'contact@tacosgordo.com'),
                    new OA\Property(property: 'website', type: 'string', format: 'uri', nullable: true, maxLength: 255, example: 'https://tacosgordo.com'),
                    new OA\Property(property: 'city', type: 'string', nullable: true, maxLength: 255, example: 'Miami'),
                    new OA\Property(property: 'state', type: 'string', nullable: true, maxLength: 50, example: 'FL'),
                    new OA\Property(property: 'country', type: 'string', nullable: true, maxLength: 50, example: 'USA'),
                    new OA\Property(property: 'logo_path', type: 'string', nullable: true, maxLength: 255, example: null),
                    new OA\Property(property: 'notes', type: 'string', nullable: true, example: 'Cliente referido por SM Florida.'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Deal cerrado. Empresa y mapas de proceso creados.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'message', type: 'string', example: 'Deal cerrado correctamente. Empresa y mapas de proceso creados.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permiso para crear empresas'),
            new OA\Response(response: 422, description: 'Error de validación'),
        ]
    )]
    public function closeDeal(StoreCompanyRequest $request): JsonResponse
    {
        $this->authorize('create', Company::class);

        $company = $this->companyService->closeDeal($request->validated());

        return response()->json([
            'success' => true,
            'data' => new CompanyResource($company),
            'message' => 'Deal cerrado correctamente. Empresa y mapas de proceso creados.',
        ], 201);
    }
}
