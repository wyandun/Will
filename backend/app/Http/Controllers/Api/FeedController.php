<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\DeleteCommentRequest;
use App\Http\Requests\Feed\DestroyPostRequest;
use App\Http\Requests\Feed\ListCommentsRequest;
use App\Http\Requests\Feed\ListPostsRequest;
use App\Http\Requests\Feed\ReactPostRequest;
use App\Http\Requests\Feed\StoreCommentRequest;
use App\Http\Requests\Feed\StorePostRequest;
use App\Http\Requests\Feed\UpdatePostRequest;
use App\Services\FeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class FeedController extends Controller
{
    public function __construct(private FeedService $feedService) {}

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
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ]
    )]
    public function posts(ListPostsRequest $request): JsonResponse
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
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
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

    #[OA\Post(
        path: '/feed/posts',
        tags: ['Feed'],
        summary: 'Crear un nuevo post en el Feed',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['title', 'body', 'type', 'visibility'],
                    properties: [
                        new OA\Property(property: 'title', type: 'string', maxLength: 255),
                        new OA\Property(property: 'body', type: 'string'),
                        new OA\Property(property: 'type', type: 'string', enum: ['announcement', 'news', 'training', 'alert']),
                        new OA\Property(property: 'visibility', type: 'string', enum: ['global', 'franchise']),
                        new OA\Property(property: 'is_pinned', type: 'boolean', nullable: true),
                        new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true),
                        new OA\Property(property: 'attachment', type: 'string', format: 'binary', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Post creado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Sin permiso para crear posts'),
            new OA\Response(response: 422, description: 'Error de validación'),
        ]
    )]
    public function store(StorePostRequest $request): JsonResponse
    {
        $user = $request->user();

        $post = $this->feedService->createPost(
            $user,
            $request->validated(),
            $request->hasFile('image') ? $request->file('image') : null,
            $request->hasFile('attachment') ? $request->file('attachment') : null,
        );

        return response()->json([
            'success' => true,
            'data' => ['post' => $post],
        ], 201);
    }

    #[OA\Patch(
        path: '/feed/posts/{id}',
        tags: ['Feed'],
        summary: 'Editar un post existente',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'title', type: 'string', maxLength: 255),
                        new OA\Property(property: 'body', type: 'string'),
                        new OA\Property(property: 'type', type: 'string', enum: ['announcement', 'news', 'training', 'alert']),
                        new OA\Property(property: 'visibility', type: 'string', enum: ['global', 'franchise']),
                        new OA\Property(property: 'is_pinned', type: 'boolean', nullable: true),
                        new OA\Property(property: 'published_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'image', type: 'string', format: 'binary', nullable: true),
                        new OA\Property(property: 'attachment', type: 'string', format: 'binary', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Post actualizado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Sin permiso para editar este post'),
            new OA\Response(response: 404, description: 'Post no encontrado'),
        ]
    )]
    public function update(UpdatePostRequest $request, int $id): JsonResponse
    {
        $post = $this->feedService->updatePost(
            $id,
            $request->user(),
            $request->validated(),
            $request->hasFile('image') ? $request->file('image') : null,
            $request->hasFile('attachment') ? $request->file('attachment') : null,
        );

        return response()->json([
            'success' => true,
            'data' => ['post' => $post],
        ]);
    }

    #[OA\Delete(
        path: '/feed/posts/{id}',
        tags: ['Feed'],
        summary: 'Eliminar un post (soft delete)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Post eliminado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Sin permiso para eliminar este post'),
            new OA\Response(response: 404, description: 'Post no encontrado'),
        ]
    )]
    public function destroy(DestroyPostRequest $request, int $id): JsonResponse
    {
        $this->feedService->deletePost($id, $request->user());

        return response()->json(['success' => true]);
    }

    #[OA\Post(
        path: '/feed/posts/{postId}/react',
        tags: ['Feed'],
        summary: 'Reaccionar (toggle) a un post con un emoji',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'postId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['emoji'],
                properties: [
                    new OA\Property(property: 'emoji', type: 'string', example: '👍', description: 'Uno de: 👍 ❤️ 😂 🎉 😮'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reacción registrada o eliminada (toggle)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'likes_count', type: 'integer', example: 3),
                                new OA\Property(property: 'user_reaction', type: 'string', nullable: true, example: '👍'),
                            ]
                        ),
                        new OA\Property(property: 'message', type: 'string', example: 'Reacción registrada.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Post no encontrado'),
            new OA\Response(response: 422, description: 'Emoji inválido'),
        ]
    )]
    public function react(ReactPostRequest $request, int $postId): JsonResponse
    {
        $result = $this->feedService->react($postId, $request->user(), $request->validated('emoji'));

        $message = $result['user_reaction'] !== null ? __('feed.reaction_added') : __('feed.reaction_removed');

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => $message,
        ]);
    }

    #[OA\Get(
        path: '/feed/posts/{postId}/comments',
        tags: ['Feed'],
        summary: 'Listar comentarios de un post (paginado)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'postId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
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
                description: 'Lista paginada de comentarios',
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
                                            new OA\Property(property: 'content', type: 'string', example: 'Excelente publicación!'),
                                            new OA\Property(property: 'author_name', type: 'string', example: 'Juan Pérez'),
                                            new OA\Property(property: 'author_avatar_url', type: 'string', nullable: true),
                                            new OA\Property(property: 'author_role', type: 'string', nullable: true, example: 'sb_owner'),
                                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                            new OA\Property(property: 'is_own', type: 'boolean', example: false),
                                        ]
                                    )
                                ),
                                new OA\Property(
                                    property: 'meta',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                        new OA\Property(property: 'last_page', type: 'integer', example: 2),
                                        new OA\Property(property: 'per_page', type: 'integer', example: 10),
                                        new OA\Property(property: 'total', type: 'integer', example: 15),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Post no encontrado'),
        ]
    )]
    public function comments(ListCommentsRequest $request, int $postId): JsonResponse
    {
        $validated = $request->validated();
        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);

        $data = $this->feedService->getComments($postId, $request->user(), $page, $perPage);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[OA\Post(
        path: '/feed/posts/{postId}/comments',
        tags: ['Feed'],
        summary: 'Agregar un comentario a un post',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'postId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['content'],
                properties: [
                    new OA\Property(property: 'content', type: 'string', maxLength: 2000, example: 'Muy buen contenido!'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Comentario publicado',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'object', properties: [
                            new OA\Property(property: 'comment', type: 'object'),
                        ]),
                        new OA\Property(property: 'message', type: 'string', example: 'Comentario publicado.'),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Post no encontrado'),
            new OA\Response(response: 422, description: 'Validación fallida'),
        ]
    )]
    public function addComment(StoreCommentRequest $request, int $postId): JsonResponse
    {
        $comment = $this->feedService->addComment($postId, $request->user(), $request->validated('content'));

        return response()->json([
            'success' => true,
            'data' => ['comment' => $comment],
            'message' => __('feed.comment_created'),
        ], 201);
    }

    #[OA\Delete(
        path: '/feed/comments/{commentId}',
        tags: ['Feed'],
        summary: 'Eliminar un comentario (físico)',
        description: 'Solo el autor del comentario o un superadmin puede eliminarlo. Se realiza borrado físico ya que la tabla no tiene deleted_at.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'commentId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Comentario eliminado correctamente'),
            new OA\Response(response: 403, description: 'Sin permiso para eliminar este comentario'),
            new OA\Response(response: 404, description: 'Comentario no encontrado'),
        ]
    )]
    public function deleteComment(DeleteCommentRequest $request, int $commentId): Response
    {
        $this->feedService->deleteComment($commentId, $request->user());

        return response()->noContent();
    }
}
