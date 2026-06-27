<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectDeliverableStatus;
use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateDeliverableStatusRequest;
use App\Http\Resources\ProjectDeliverableResource;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Models\ProjectDeliverable;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ProjectController extends Controller
{
    public function __construct(private ProjectService $projectService) {}

    #[OA\Get(
        path: '/projects',
        tags: ['Projects'],
        summary: 'Listar proyectos visibles para el usuario',
        description: 'Superadmin ve todos. admin_sm ve los de su franquicia. Soporta filtros: ?search= y ?status=',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Filtra por nombre del proyecto o nombre de la empresa (ILIKE)',
                schema: new OA\Schema(type: 'string')
            ),
            new OA\Parameter(
                name: 'status',
                in: 'query',
                required: false,
                description: 'Filtra por estado',
                schema: new OA\Schema(type: 'string', enum: ['active', 'completed', 'paused', 'cancelled'])
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de proyectos'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $user = $request->user();

        $query = Project::with(['company', 'franchise', 'catalogItem', 'deliverables']);

        // Scope to franchise for admin_sm.
        if ($user->hasRole(Role::ADMIN_SM)) {
            $query->where('franchise_id', $user->sm_franchise_id);
        }

        // Filter by search term — matches company name or catalog item name (case-insensitive).
        if ($search = $request->query('search')) {
            $pattern = '%'.mb_strtolower($search).'%';

            $query->where(function ($q) use ($pattern) {
                $q->whereHas('company', fn ($q2) => $q2->whereRaw('LOWER(name) LIKE ?', [$pattern]))
                    ->orWhereHas('catalogItem', fn ($q2) => $q2->whereRaw('LOWER(name_es) LIKE ?', [$pattern]));
            });
        }

        // Filter by status.
        if ($status = $request->query('status')) {
            $statusEnum = ProjectStatus::tryFrom($status);

            if ($statusEnum !== null) {
                $query->where('status', $statusEnum->value);
            }
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

    #[OA\Patch(
        path: '/projects/{project}/deliverables/{deliverable}',
        tags: ['Projects'],
        summary: 'Actualizar el estado de un entregable',
        description: 'Cambia el estado de un project_deliverable y retorna el entregable actualizado junto con el progress_percentage recalculado del proyecto.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'deliverable', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'completed', 'blocked']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Entregable actualizado con progress_percentage del proyecto'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
            new OA\Response(response: 422, description: 'Validación', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ]
    )]
    #[OA\Get(
        path: '/projects/{project}/upcoming-deliverables',
        tags: ['Projects'],
        summary: 'Obtener los entregables próximos de un proyecto',
        description: 'Retorna los entregables con estado pending o in_progress, ordenados por fecha de fin estimada.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lista de entregables próximos'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ]
    )]
    public function upcomingDeliverables(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $upcomingStatuses = array_map(
            fn (ProjectDeliverableStatus $s) => $s->value,
            ProjectDeliverableStatus::upcoming()
        );

        $deliverables = $project->deliverables()
            ->whereIn('status', $upcomingStatuses)
            ->orderBy('estimated_end_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $deliverables->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'phase' => $d->phase,
                'estimated_end_date' => $d->estimated_end_date?->toDateString(),
                'status' => $d->status->value,
            ]),
        ]);
    }

    public function updateDeliverableStatus(
        UpdateDeliverableStatusRequest $request,
        Project $project,
        ProjectDeliverable $deliverable
    ): JsonResponse {
        // Ensure the deliverable belongs to this project.
        if ($deliverable->project_id !== $project->id) {
            abort(404);
        }

        $this->authorize('update', $project);

        $deliverable->update(['status' => $request->validated('status')]);

        // Recalculate progress after the update.
        $project->load('deliverables');
        $total = $project->deliverables->count();
        $completed = $project->deliverables->filter(fn ($d) => $d->status->value === 'completed')->count();
        $progressPercentage = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'deliverable' => new ProjectDeliverableResource($deliverable->fresh()),
                'progress_percentage' => $progressPercentage,
                'deliverables_completed' => $completed,
                'deliverables_total' => $total,
            ],
        ]);
    }
}
