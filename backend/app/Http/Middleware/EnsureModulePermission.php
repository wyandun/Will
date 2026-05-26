<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModulePermission
{
    /**
     * Write methods — require can_write = true in addition to can_read.
     *
     * @var list<string>
     */
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Verify that the authenticated user has access to the requested module.
     *
     * Route usage:
     *   Route::middleware('module.permission:accounting')
     *
     * Superadmin bypasses all module permission checks.
     * For other roles, the user must have a row in user_permissions for the
     * module with can_read = true. Write methods also require can_write = true.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        // Superadmin has unrestricted access to every module.
        if ($user->hasRole(Role::SUPERADMIN)) {
            return $next($request);
        }

        $permission = $user->userPermissions()
            ->where('module', $module)
            ->first();

        // No row for this module → deny.
        if (! $permission || ! $permission->can_read) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'No tienes permiso para acceder a este módulo.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Write operations require explicit write permission.
        if (in_array($request->method(), self::WRITE_METHODS, true) && ! $permission->can_write) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'No tienes permiso para realizar esta acción en el módulo.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
