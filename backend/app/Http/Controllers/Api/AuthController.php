<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    /**
     * Authenticate a user and return a Sanctum token.
     *
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => 'Inicio de sesión exitoso.',
        ]);
    }

    /**
     * Return the currently authenticated user's data.
     *
     * GET /api/v1/auth/me  (requires auth:sanctum)
     */
    public function me(Request $request): JsonResponse
    {
        $result = $this->authService->me($request->user());

        return response()->json([
            'success' => true,
            'data'    => $result,
            'message' => 'OK.',
        ]);
    }

    /**
     * Revoke all tokens for the authenticated user.
     *
     * POST /api/v1/auth/logout  (requires auth:sanctum)
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'success' => true,
            'data'    => null,
            'message' => 'Sesión cerrada correctamente.',
        ]);
    }
}
