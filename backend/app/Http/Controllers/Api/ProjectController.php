<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projectService) {}

    #[OA\Get(
        path: '/projects',
        tags: ['Projects'],
        summary: 'Listar proyectos visibles para el usuario',
        description: 'Superadmin ve todos. admin_sm ve los de su franquicia.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Lista de proyectos'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $user = request()->user();

        $query = Project::with(['company', 'franchise', 'catalogItem', 'deliverables']);

        if ($user->hasRole(Role::ADMIN_SM)) {
            $query->where('franchise_id', $user->sm_franchise_id);
        }

        $projects = $query->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'data' => ProjectResource::collection($projects),
        ]);
    }

    #[OA\Post(
        path: '/projects',
        tags: ['Projects'],
        summary: 'Crear un proyecto y generar el cronograma de entregables',
        description: 'Asigna un bundle, servicio o entregable del catálogo a una empresa. Genera automáticamente los project_deliverables con fechas secuenciales.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['company_id', 'franchise_id', 'catalog_item_id', 'type', 'start_date'],
                properties: [
                    new OA\Property(property: 'company_id', type: 'integer'),
                    new OA\Property(property: 'franchise_id', type: 'integer'),
                    new OA\Property(property: 'catalog_item_id', type: 'integer'),
                    new OA\Property(property: 'type', type: 'string', enum: ['bundle', 'service', 'deliverable']),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date'),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Proyecto creado con entregables'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, description: 'Validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        $project = $this->projectService->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new ProjectResource($project),
            'message' => 'projects.created_success',
        ], 201);
    }

    #[OA\Get(
        path: '/projects/{project}',
        tags: ['Projects'],
        summary: 'Obtener un proyecto por ID',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Proyecto con entregables'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->load(['company', 'franchise', 'catalogItem', 'deliverables']);

        return response()->json([
            'success' => true,
            'data' => new ProjectResource($project),
        ]);
    }
}
