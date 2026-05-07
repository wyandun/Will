<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BbAssignment\StoreBbAssignmentRequest;
use App\Models\BbAssignment;
use App\Services\BbAssignmentService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class BbAssignmentController extends Controller
{
    public function __construct(private BbAssignmentService $bbAssignmentService) {}

    #[OA\Post(
        path: '/bb-assignments',
        tags: ['BB Assignments'],
        summary: 'Asignar un Business Bishop a una empresa',
        description: 'Vincula un usuario con rol bb a una empresa. Un BB puede estar asignado a múltiples empresas. Solo superadmin y admin_sm (dentro de su franquicia) pueden realizar esta acción.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['bb_user_id', 'company_id'],
                properties: [
                    new OA\Property(
                        property: 'bb_user_id',
                        type: 'integer',
                        example: 8,
                        description: 'ID del usuario que debe tener el rol bb'
                    ),
                    new OA\Property(
                        property: 'company_id',
                        type: 'integer',
                        example: 3,
                        description: 'ID de la empresa a la que se asigna el BB. admin_sm solo puede asignar empresas de su franquicia.'
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Business Bishop asignado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'bb_user_id', type: 'integer', example: 8),
                                new OA\Property(property: 'company_id', type: 'integer', example: 3),
                                new OA\Property(property: 'assigned_by', type: 'integer', example: 1),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                        new OA\Property(property: 'message', type: 'string', example: 'Business Bishop asignado correctamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permiso para asignar BBs'),
            new OA\Response(response: 422, description: 'Error de validación (usuario no tiene rol bb, empresa fuera de scope, etc.)'),
        ]
    )]
    public function store(StoreBbAssignmentRequest $request): JsonResponse
    {
        $this->authorize('create', BbAssignment::class);

        $user = $request->user();

        $assignment = $this->bbAssignmentService->assign($request->validated(), $user);

        return response()->json([
            'success' => true,
            'data' => $assignment,
            'message' => 'Business Bishop asignado correctamente.',
        ], 201);
    }

    #[OA\Delete(
        path: '/bb-assignments/{id}',
        tags: ['BB Assignments'],
        summary: 'Desasignar un Business Bishop de una empresa',
        description: 'Elimina el vínculo BB-empresa. Solo superadmin y admin_sm pueden realizar esta acción.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del registro bb_assignment',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Business Bishop desasignado correctamente',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'message', type: 'string', example: 'Business Bishop desasignado correctamente.'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'No autenticado'),
            new OA\Response(response: 403, description: 'Sin permiso para desasignar BBs'),
            new OA\Response(response: 404, description: 'Asignación no encontrada'),
        ]
    )]
    public function destroy(BbAssignment $bbAssignment): JsonResponse
    {
        $this->authorize('delete', $bbAssignment);

        $this->bbAssignmentService->unassign($bbAssignment);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Business Bishop desasignado correctamente.',
        ]);
    }
}
