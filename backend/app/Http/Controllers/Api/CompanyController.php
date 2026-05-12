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
                description: 'Lista paginada de empresas',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/CompanyResource')
                        ),
                        new OA\Property(
                            property: 'links',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'first', type: 'string', nullable: true, example: 'http://example.com/api/v1/companies?page=1'),
                                new OA\Property(property: 'last', type: 'string', nullable: true, example: 'http://example.com/api/v1/companies?page=5'),
                                new OA\Property(property: 'prev', type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'next', type: 'string', nullable: true, example: 'http://example.com/api/v1/companies?page=2'),
                            ]
                        ),
                        new OA\Property(
                            property: 'meta',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page', type: 'integer', example: 5),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                new OA\Property(property: 'total', type: 'integer', example: 73),
                                new OA\Property(property: 'path', type: 'string', example: 'http://example.com/api/v1/companies'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
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
                allOf: [
                    new OA\Schema(ref: '#/components/schemas/CompanyWriteInput'),
                    new OA\Schema(required: ['name', 'sm_franchise_id']),
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
                        new OA\Property(property: 'data', ref: '#/components/schemas/CompanyResource'),
                        new OA\Property(property: 'message', type: 'string', example: 'Empresa creada correctamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(
                response: 422,
                description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
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
        path: '/companies/{company}',
        tags: ['Companies'],
        summary: 'Obtener una empresa por ID',
        description: 'Retorna los datos completos de una empresa, incluyendo el nombre de su franquicia SM asociada.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'company',
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
                        new OA\Property(property: 'data', ref: '#/components/schemas/CompanyResource'),
                        new OA\Property(property: 'message', type: 'string', example: 'OK.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
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

    #[OA\Patch(
        path: '/companies/{company}',
        tags: ['Companies'],
        summary: 'Actualizar una empresa existente',
        description: 'Todos los campos son opcionales. Solo se actualizan los campos enviados.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'company',
                in: 'path',
                required: true,
                description: 'ID de la empresa',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CompanyWriteInput')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Empresa actualizada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/CompanyResource'),
                        new OA\Property(property: 'message', type: 'string', example: 'Empresa actualizada correctamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(
                response: 422,
                description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
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
        path: '/companies/{company}',
        tags: ['Companies'],
        summary: 'Eliminar una empresa',
        description: 'Solo superadmin puede eliminar empresas.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'company',
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
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                        new OA\Property(property: 'message', type: 'string', example: 'Empresa eliminada correctamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
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
                allOf: [
                    new OA\Schema(ref: '#/components/schemas/CompanyWriteInput'),
                    new OA\Schema(required: ['name', 'sm_franchise_id']),
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
                        new OA\Property(property: 'data', ref: '#/components/schemas/CompanyResource'),
                        new OA\Property(property: 'message', type: 'string', example: 'Deal cerrado correctamente. Empresa y mapas de proceso creados.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(
                response: 422,
                description: 'Error de validación',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
            ),
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
