<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Franchise\DestroyFranchiseRequest;
use App\Http\Requests\Franchise\StoreFranchiseRequest;
use App\Http\Requests\Franchise\ToggleFranchiseStatusRequest;
use App\Http\Requests\Franchise\UpdateFranchiseRequest;
use App\Http\Resources\FranchiseResource;
use App\Models\Franchise;
use App\Services\FranchiseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class FranchiseController extends Controller
{
    public function __construct(private FranchiseService $franchiseService) {}

    #[OA\Get(
        path: '/franchises',
        tags: ['Franchises'],
        summary: 'Listar franquicias visibles para el usuario autenticado',
        description: 'Superadmin ve todas las franquicias. admin_sm solo ve la suya.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Filtrar por nombre, región o país',
                schema: new OA\Schema(type: 'string', example: 'Florida')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de franquicias',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'SM Florida'),
                                    new OA\Property(property: 'type', type: 'string', enum: ['sm', 'sub'], example: 'sm'),
                                    new OA\Property(property: 'parent_company_id', type: 'integer', nullable: true, example: null),
                                    new OA\Property(property: 'owner_user_id', type: 'integer', nullable: true, example: 5),
                                    new OA\Property(property: 'region', type: 'string', nullable: true, example: 'Southeast'),
                                    new OA\Property(property: 'email', type: 'string', nullable: true, example: 'florida@strategicmates.com'),
                                    new OA\Property(property: 'country', type: 'string', nullable: true, example: 'USA'),
                                    new OA\Property(property: 'timezone', type: 'string', nullable: true, example: 'America/New_York'),
                                    new OA\Property(property: 'address', type: 'string', nullable: true, example: '123 Main St, Miami FL'),
                                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '+13055550100'),
                                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                                    new OA\Property(property: 'admins_count', type: 'integer', nullable: true, example: 3),
                                    new OA\Property(property: 'clients_count', type: 'integer', nullable: true, example: 12),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                                ]
                            )
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
        $this->authorize('viewAny', Franchise::class);

        $franchises = $this->franchiseService->list($request->user());

        return FranchiseResource::collection($franchises);
    }

    #[OA\Post(
        path: '/franchises',
        tags: ['Franchises'],
        summary: 'Crear una nueva franquicia',
        description: 'Solo superadmin puede crear franquicias.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'type'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'SM Texas'),
                    new OA\Property(property: 'type', type: 'string', enum: ['sm', 'sub'], example: 'sm'),
                    new OA\Property(property: 'parent_company_id', type: 'integer', nullable: true, example: null),
                    new OA\Property(property: 'owner_user_id', type: 'integer', nullable: true, example: 7),
                    new OA\Property(property: 'region', type: 'string', nullable: true, maxLength: 255, example: 'Southwest'),
                    new OA\Property(property: 'address', type: 'string', nullable: true, maxLength: 255, example: '456 Oak Ave, Houston TX'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, maxLength: 30, example: '+17135550200'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, maxLength: 255, example: 'texas@strategicmates.com'),
                    new OA\Property(property: 'country', type: 'string', nullable: true, maxLength: 255, example: 'USA'),
                    new OA\Property(property: 'timezone', type: 'string', nullable: true, example: 'America/Chicago'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Franquicia creada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'message', type: 'string', example: 'franchises.created_success'),
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
    public function store(StoreFranchiseRequest $request): JsonResponse
    {
        $this->authorize('create', Franchise::class);

        $franchise = $this->franchiseService->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new FranchiseResource($franchise),
            'message' => 'franchises.created_success',
        ], 201);
    }

    #[OA\Get(
        path: '/franchises/{id}',
        tags: ['Franchises'],
        summary: 'Obtener una franquicia por ID',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la franquicia',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Datos de la franquicia',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'message', type: 'string', example: 'OK.'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(Franchise $franchise): JsonResponse
    {
        $this->authorize('view', $franchise);

        return response()->json([
            'success' => true,
            'data' => new FranchiseResource($franchise),
            'message' => 'OK.',
        ]);
    }

    #[OA\Patch(
        path: '/franchises/{id}',
        tags: ['Franchises'],
        summary: 'Actualizar una franquicia existente',
        description: 'Todos los campos son opcionales. Solo se actualizan los campos enviados. Solo superadmin puede actualizar franquicias.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la franquicia',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'SM Florida Updated'),
                    new OA\Property(property: 'type', type: 'string', enum: ['sm', 'sub'], example: 'sm'),
                    new OA\Property(property: 'parent_company_id', type: 'integer', nullable: true, example: null),
                    new OA\Property(property: 'owner_user_id', type: 'integer', nullable: true, example: 5),
                    new OA\Property(property: 'region', type: 'string', nullable: true, maxLength: 255, example: 'Southeast'),
                    new OA\Property(property: 'address', type: 'string', nullable: true, maxLength: 255, example: '123 Main St, Miami FL'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, maxLength: 30, example: '+13055550100'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true, maxLength: 255, example: 'florida@strategicmates.com'),
                    new OA\Property(property: 'country', type: 'string', nullable: true, maxLength: 255, example: 'USA'),
                    new OA\Property(property: 'timezone', type: 'string', nullable: true, example: 'America/New_York'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Franquicia actualizada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'message', type: 'string', example: 'franchises.updated_success'),
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
    public function update(UpdateFranchiseRequest $request, Franchise $franchise): JsonResponse
    {
        $this->authorize('update', $franchise);

        $franchise = $this->franchiseService->update($franchise, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new FranchiseResource($franchise),
            'message' => 'franchises.updated_success',
        ]);
    }

    #[OA\Patch(
        path: '/franchises/{id}/toggle-status',
        tags: ['Franchises'],
        summary: 'Activar o desactivar una franquicia',
        description: 'Alterna el campo is_active. Si estaba activa la desactiva y viceversa.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la franquicia',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Estado de la franquicia actualizado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                        new OA\Property(property: 'message', type: 'string', example: 'franchises.activated_success'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function toggleStatus(ToggleFranchiseStatusRequest $request, Franchise $franchise): JsonResponse
    {
        $this->authorize('toggleStatus', $franchise);

        $franchise = $this->franchiseService->toggleStatus($franchise);

        return response()->json([
            'success' => true,
            'data' => new FranchiseResource($franchise),
            'message' => $franchise->is_active
                ? 'franchises.activated_success'
                : 'franchises.deactivated_success',
        ]);
    }

    #[OA\Delete(
        path: '/franchises/{id}',
        tags: ['Franchises'],
        summary: 'Eliminar una franquicia',
        description: 'Solo superadmin puede eliminar franquicias.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la franquicia',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Franquicia eliminada correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                        new OA\Property(property: 'message', type: 'string', example: 'franchises.deleted_success'),
                    ]
                )
            ),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function destroy(DestroyFranchiseRequest $request, Franchise $franchise): JsonResponse
    {
        $this->authorize('delete', $franchise);

        $this->franchiseService->delete($franchise);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'franchises.deleted_success',
        ]);
    }
}
