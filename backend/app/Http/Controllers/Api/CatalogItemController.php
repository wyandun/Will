<?php

namespace App\Http\Controllers\Api;

use App\Enums\CatalogLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogItem\StoreCatalogItemRequest;
use App\Http\Requests\CatalogItem\UpdateCatalogItemRequest;
use App\Http\Resources\CatalogItemResource;
use App\Models\CatalogItem;
use App\Services\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class CatalogItemController extends Controller
{
    public function __construct(private CatalogService $catalogService) {}

    #[OA\Get(
        path: '/catalog-items',
        tags: ['Catalog'],
        summary: 'Listar items del catálogo filtrados por level',
        description: 'Devuelve una colección plana filtrada por level. Para obtener el árbol completo usar GET /catalog-items/tree.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'level',
                in: 'query',
                required: true,
                description: 'Nivel jerárquico a listar',
                schema: new OA\Schema(type: 'string', enum: ['bundle', 'service', 'deliverable'])
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Colección de items'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, description: 'Validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CatalogItem::class);

        $request->validate([
            'level' => ['required', 'string', Rule::enum(CatalogLevel::class)],
        ]);

        return CatalogItemResource::collection(
            $this->catalogService->list($request->query('level'))
        );
    }

    #[OA\Get(
        path: '/catalog-items/tree',
        tags: ['Catalog'],
        summary: 'Obtener el catálogo completo en forma de árbol con conteos',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Árbol completo + conteos'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function tree(): JsonResponse
    {
        $this->authorize('viewAny', CatalogItem::class);

        $tree = $this->catalogService->tree();

        return response()->json([
            'success' => true,
            'data' => [
                'bundles' => CatalogItemResource::collection($tree['bundles']),
                'services' => CatalogItemResource::collection($tree['services']),
                'counts' => $tree['counts'],
            ],
        ]);
    }

    #[OA\Post(
        path: '/catalog-items',
        tags: ['Catalog'],
        summary: 'Crear un nuevo item del catálogo',
        description: 'Solo superadmin. level=bundle|service|deliverable. Para reasignar hijos enviar deliverable_ids o service_ids.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['level', 'name_es', 'name_en'],
                properties: [
                    new OA\Property(property: 'level', type: 'string', enum: ['bundle', 'service', 'deliverable']),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'name_es', type: 'string', maxLength: 255),
                    new OA\Property(property: 'name_en', type: 'string', maxLength: 255),
                    new OA\Property(property: 'description_es', type: 'string', nullable: true),
                    new OA\Property(property: 'description_en', type: 'string', nullable: true),
                    new OA\Property(property: 'is_monthly', type: 'boolean'),
                    new OA\Property(property: 'order_index', type: 'integer', minimum: 0),
                    new OA\Property(property: 'estimated_hours', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'service_type', type: 'string', enum: ['individual', 'package', 'retainer'], nullable: true),
                    new OA\Property(property: 'deliverable_ids', type: 'array', items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'service_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Creado'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, description: 'Validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(StoreCatalogItemRequest $request): JsonResponse
    {
        $this->authorize('create', CatalogItem::class);

        $item = $this->catalogService->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new CatalogItemResource($item),
            'message' => 'catalog.created_success',
        ], 201);
    }

    #[OA\Get(
        path: '/catalog-items/{id}',
        tags: ['Catalog'],
        summary: 'Obtener un item del catálogo',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Item'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(CatalogItem $catalogItem): JsonResponse
    {
        $this->authorize('view', $catalogItem);

        // Load grandchildren as well so the tree is complete for bundles
        // (bundle → services → deliverables).
        $catalogItem->load(['parent', 'children.children']);

        return response()->json([
            'success' => true,
            'data' => new CatalogItemResource($catalogItem),
        ]);
    }

    #[OA\Patch(
        path: '/catalog-items/{id}',
        tags: ['Catalog'],
        summary: 'Actualizar un item del catálogo',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'level', type: 'string', enum: ['bundle', 'service', 'deliverable']),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'name_es', type: 'string'),
                    new OA\Property(property: 'name_en', type: 'string'),
                    new OA\Property(property: 'description_es', type: 'string', nullable: true),
                    new OA\Property(property: 'description_en', type: 'string', nullable: true),
                    new OA\Property(property: 'is_monthly', type: 'boolean'),
                    new OA\Property(property: 'order_index', type: 'integer'),
                    new OA\Property(property: 'estimated_hours', type: 'number', format: 'float', nullable: true),
                    new OA\Property(property: 'service_type', type: 'string', enum: ['individual', 'package', 'retainer'], nullable: true),
                    new OA\Property(property: 'deliverable_ids', type: 'array', items: new OA\Items(type: 'integer')),
                    new OA\Property(property: 'service_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Actualizado'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, description: 'Validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function update(UpdateCatalogItemRequest $request, CatalogItem $catalogItem): JsonResponse
    {
        $this->authorize('update', $catalogItem);

        $item = $this->catalogService->update($catalogItem, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new CatalogItemResource($item),
            'message' => 'catalog.updated_success',
        ]);
    }

    #[OA\Delete(
        path: '/catalog-items/{id}',
        tags: ['Catalog'],
        summary: 'Eliminar un item del catálogo',
        description: 'Con ?cascade_children=true los hijos se eliminan junto con el item (solo aplica a services). Sin el parámetro quedan huérfanos.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(
                name: 'cascade_children',
                in: 'query',
                required: false,
                description: 'true para eliminar los hijos en cascada (solo aplica a services)',
                schema: new OA\Schema(type: 'boolean')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Eliminado (sin contenido)'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, description: 'Validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function destroy(Request $request, CatalogItem $catalogItem): Response|JsonResponse
    {
        $this->authorize('delete', $catalogItem);

        $request->validate(['cascade_children' => ['nullable', 'boolean']]);
        $cascade = $request->boolean('cascade_children');

        if ($cascade && $catalogItem->level !== CatalogLevel::Service) {
            return response()->json([
                'success' => false,
                'message' => 'cascade_children only applies to service-level items.',
            ], 422);
        }

        $this->catalogService->delete($catalogItem, $cascade);

        return response()->noContent();
    }
}
