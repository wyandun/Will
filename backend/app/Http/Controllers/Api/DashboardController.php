<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    // Base URL /api/v1 is set in config/l5-swagger.php servers entry (app/OpenApi/ApiInfo.php).
    #[OA\Get(
        path: '/dashboard',
        tags: ['Dashboard'],
        summary: 'Obtener todos los datos del dashboard en una sola llamada',
        description: 'Retorna KPIs, feed reciente, próximos eventos, proyectos activos, contratos, documentos y mapas de proceso. Cada sección está filtrada por el scope del usuario autenticado.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Datos completos del dashboard',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'kpis',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'events_next_14_days', type: 'integer', example: 3),
                                        new OA\Property(property: 'pending_signature', type: 'integer', example: 2),
                                        new OA\Property(property: 'projects_active', type: 'integer', example: 8),
                                        new OA\Property(property: 'to_review', type: 'integer', example: 5),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'feed',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 10),
                                            new OA\Property(property: 'title', type: 'string', example: 'Nuevo programa de capacitación'),
                                            new OA\Property(property: 'author_name', type: 'string', example: 'Super Admin'),
                                            new OA\Property(property: 'author_avatar', type: 'string', nullable: true),
                                            new OA\Property(property: 'content', type: 'string', example: 'Descripción del post...'),
                                            new OA\Property(property: 'image_path', type: 'string', nullable: true),
                                            new OA\Property(property: 'likes_count', type: 'integer', example: 4),
                                            new OA\Property(property: 'comments_count', type: 'integer', example: 1),
                                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'events',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 5),
                                            new OA\Property(property: 'title', type: 'string', example: 'Reunión mensual SM'),
                                            new OA\Property(property: 'start_at', type: 'string', format: 'date-time'),
                                            new OA\Property(property: 'end_at', type: 'string', format: 'date-time', nullable: true),
                                            new OA\Property(property: 'all_day', type: 'boolean', example: false),
                                            new OA\Property(property: 'color', type: 'string', nullable: true, example: '#3B82F6'),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'tracking',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 3),
                                            new OA\Property(property: 'company_name', type: 'string', example: 'Tacos El Gordo LLC'),
                                            new OA\Property(property: 'item_name', type: 'string', example: 'Manual de Operaciones'),
                                            new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'review'], example: 'in_progress'),
                                            new OA\Property(property: 'progress_percent', type: 'integer', example: 60),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'contracts',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'pending', type: 'integer', example: 2),
                                        new OA\Property(property: 'signed', type: 'integer', example: 11),
                                        new OA\Property(
                                            property: 'recent',
                                            type: 'array',
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 7),
                                                    new OA\Property(property: 'title', type: 'string', example: 'Contrato de franquicia 2026'),
                                                    new OA\Property(property: 'status', type: 'string', example: 'sent'),
                                                    new OA\Property(property: 'company_name', type: 'string', example: 'Tacos El Gordo LLC'),
                                                ]
                                            )
                                        ),
                                    ]
                                ),
                                new OA\Property(
                                    property: 'documents',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 15),
                                            new OA\Property(property: 'title', type: 'string', example: 'Manual de Marca v2'),
                                            new OA\Property(property: 'source', type: 'string', example: 'Repository'),
                                            new OA\Property(property: 'file_type', type: 'string', example: 'pdf'),
                                            new OA\Property(property: 'days_ago', type: 'integer', example: 3),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'process_maps',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 2),
                                            new OA\Property(property: 'company_name', type: 'string', example: 'Tacos El Gordo LLC'),
                                            new OA\Property(property: 'name', type: 'string', example: 'Mapa Franquiciadora'),
                                            new OA\Property(property: 'type', type: 'string', enum: ['franquiciadora', 'franquiciada'], example: 'franquiciadora'),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => $this->dashboardService->getKpis($user),
                'feed' => $this->dashboardService->getFeed($user),
                'events' => $this->dashboardService->getEvents($user),
                'tracking' => $this->dashboardService->getTracking($user),
                'contracts' => $this->dashboardService->getContracts($user),
                'documents' => $this->dashboardService->getDocuments($user),
                'process_maps' => $this->dashboardService->getProcessMaps($user),
            ],
        ]);
    }

    #[OA\Get(
        path: '/dashboard/kpis',
        tags: ['Dashboard'],
        summary: 'KPIs del dashboard',
        description: 'Eventos próximos 14 días, contratos pendientes de firma, proyectos activos y documentos a revisar.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'KPIs del usuario',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'events_next_14_days', type: 'integer', example: 3),
                                new OA\Property(property: 'pending_signature', type: 'integer', example: 2),
                                new OA\Property(property: 'projects_active', type: 'integer', example: 8),
                                new OA\Property(property: 'to_review', type: 'integer', example: 5),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function kpis(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getKpis($request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[OA\Get(
        path: '/dashboard/feed',
        tags: ['Dashboard'],
        summary: 'Posts recientes del Feed para el dashboard',
        description: 'Retorna hasta 5 posts más recientes visibles para el usuario, ordenados por pinned y fecha.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Posts recientes del Feed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 10),
                                    new OA\Property(property: 'title', type: 'string', example: 'Nuevo programa de capacitación'),
                                    new OA\Property(property: 'author_name', type: 'string', example: 'Super Admin'),
                                    new OA\Property(property: 'author_avatar', type: 'string', nullable: true),
                                    new OA\Property(property: 'content', type: 'string', example: 'Descripción del post...'),
                                    new OA\Property(property: 'image_path', type: 'string', nullable: true),
                                    new OA\Property(property: 'likes_count', type: 'integer', example: 4),
                                    new OA\Property(property: 'comments_count', type: 'integer', example: 1),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function feed(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getFeed($request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[OA\Get(
        path: '/dashboard/events',
        tags: ['Dashboard'],
        summary: 'Próximos eventos para el dashboard',
        description: 'Retorna hasta 5 eventos futuros visibles para el usuario (public, franchise, private compartidos).',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Próximos eventos',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 5),
                                    new OA\Property(property: 'title', type: 'string', example: 'Reunión mensual SM'),
                                    new OA\Property(property: 'start_at', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'end_at', type: 'string', format: 'date-time', nullable: true),
                                    new OA\Property(property: 'all_day', type: 'boolean', example: false),
                                    new OA\Property(property: 'color', type: 'string', nullable: true, example: '#3B82F6'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function events(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getEvents($request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[OA\Get(
        path: '/dashboard/tracking',
        tags: ['Dashboard'],
        summary: 'Proyectos activos para el dashboard',
        description: 'Retorna hasta 5 proyectos con estado pending, in_progress o review dentro del scope del usuario.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Proyectos activos',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 3),
                                    new OA\Property(property: 'company_name', type: 'string', example: 'Tacos El Gordo LLC'),
                                    new OA\Property(property: 'item_name', type: 'string', example: 'Manual de Operaciones'),
                                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'in_progress', 'review'], example: 'in_progress'),
                                    new OA\Property(property: 'progress_percent', type: 'integer', example: 60),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function tracking(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getTracking($request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[OA\Get(
        path: '/dashboard/contracts',
        tags: ['Dashboard'],
        summary: 'Resumen de contratos para el dashboard',
        description: 'Retorna conteo de contratos pendientes y firmados, más los 3 contratos más recientes del scope del usuario.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Resumen de contratos',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'pending', type: 'integer', example: 2),
                                new OA\Property(property: 'signed', type: 'integer', example: 11),
                                new OA\Property(
                                    property: 'recent',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 7),
                                            new OA\Property(property: 'title', type: 'string', example: 'Contrato de franquicia 2026'),
                                            new OA\Property(property: 'status', type: 'string', example: 'sent'),
                                            new OA\Property(property: 'company_name', type: 'string', example: 'Tacos El Gordo LLC'),
                                        ]
                                    )
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function contracts(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getContracts($request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[OA\Get(
        path: '/dashboard/documents',
        tags: ['Dashboard'],
        summary: 'Documentos recientes para el dashboard',
        description: 'Retorna hasta 20 documentos actuales (is_current=true) del repositorio dentro del scope del usuario.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Documentos recientes',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 15),
                                    new OA\Property(property: 'title', type: 'string', example: 'Manual de Marca v2'),
                                    new OA\Property(property: 'source', type: 'string', example: 'Repository'),
                                    new OA\Property(property: 'file_type', type: 'string', example: 'pdf'),
                                    new OA\Property(property: 'days_ago', type: 'integer', example: 3),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function documents(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getDocuments($request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[OA\Get(
        path: '/dashboard/process-maps',
        tags: ['Dashboard'],
        summary: 'Mapas de proceso para el dashboard',
        description: 'Retorna hasta 5 mapas de proceso del scope del usuario.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Mapas de proceso',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 2),
                                    new OA\Property(property: 'company_name', type: 'string', example: 'Tacos El Gordo LLC'),
                                    new OA\Property(property: 'name', type: 'string', example: 'Mapa Franquiciadora'),
                                    new OA\Property(property: 'type', type: 'string', enum: ['franquiciadora', 'franquiciada'], example: 'franquiciadora'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function processMaps(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getProcessMaps($request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
