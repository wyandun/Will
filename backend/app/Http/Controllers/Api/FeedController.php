<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class FeedController extends Controller
{
    public function __construct(private FeedService $feedService) {}

    // Base URL /api/v1 is set in config/l5-swagger.php servers entry (app/OpenApi/ApiInfo.php).
    #[OA\Get(
        path: '/feed/posts',
        tags: ['Feed'],
        summary: 'Listar posts del Feed (paginado)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Filtrar por título, contenido o autor',
                schema: new OA\Schema(type: 'string', example: 'franquicia')
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Número de página (empieza en 1)',
                schema: new OA\Schema(type: 'integer', example: 1, minimum: 1)
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Resultados por página (5–50, default 10)',
                schema: new OA\Schema(type: 'integer', example: 10, minimum: 5, maximum: 50)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista paginada de posts visibles para el usuario',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'items',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'title', type: 'string', example: 'Nuevo programa de capacitación'),
                                            new OA\Property(property: 'body', type: 'string', example: 'Contenido del post...'),
                                            new OA\Property(property: 'type', type: 'string', enum: ['announcement', 'news', 'training', 'alert'], example: 'news'),
                                            new OA\Property(property: 'is_pinned', type: 'boolean', example: false),
                                            new OA\Property(property: 'image_url', type: 'string', nullable: true, example: 'http://localhost/storage/posts/img.jpg'),
                                            new OA\Property(property: 'author_name', type: 'string', example: 'Super Admin'),
                                            new OA\Property(property: 'author_avatar', type: 'string', nullable: true, example: 'http://localhost/storage/avatars/1_abc.jpg'),
                                            new OA\Property(property: 'likes_count', type: 'integer', example: 5),
                                            new OA\Property(property: 'comments_count', type: 'integer', example: 2),
                                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'meta',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                        new OA\Property(property: 'last_page', type: 'integer', example: 3),
                                        new OA\Property(property: 'per_page', type: 'integer', example: 10),
                                        new OA\Property(property: 'total', type: 'integer', example: 25),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
        ]
    )]
    public function posts(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(5, (int) $request->query('per_page', 10)));

        $data = $this->feedService->getPosts($request->user(), $search ?: null, $page, $perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[OA\Get(
        path: '/feed/presence',
        tags: ['Feed'],
        summary: 'Usuarios online y activos recientemente',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paneles de presencia del Feed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'online',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'name', type: 'string', example: 'Benitez Aquiles'),
                                            new OA\Property(property: 'avatar_url', type: 'string', nullable: true, example: 'http://localhost/storage/avatars/1_abc.jpg'),
                                            new OA\Property(property: 'role', type: 'string', example: 'superadmin'),
                                            new OA\Property(property: 'last_seen_at', type: 'string', format: 'date-time'),
                                            new OA\Property(property: 'is_current_user', type: 'boolean', example: true),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'recently_active',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 2),
                                            new OA\Property(property: 'name', type: 'string', example: 'Juan Pérez'),
                                            new OA\Property(property: 'avatar_url', type: 'string', nullable: true),
                                            new OA\Property(property: 'role', type: 'string', example: 'admin_sm'),
                                            new OA\Property(property: 'last_seen_at', type: 'string', format: 'date-time'),
                                            new OA\Property(property: 'is_current_user', type: 'boolean', example: false),
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
    public function presence(Request $request): JsonResponse
    {
        $data = $this->feedService->getPresence($request->user());

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
