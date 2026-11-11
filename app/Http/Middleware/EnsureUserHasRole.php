<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Memastikan pengguna yang terautentikasi memiliki salah satu peran (role)
     * yang diizinkan di tingkat route middleware (Defense-in-Depth RBAC).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $userRole = strtoupper((string) ($user?->peran ?? ''));
        $allowedRoles = array_map(static fn (string $role): string => strtoupper(trim($role)), $roles);

        if (! $user || ! in_array($userRole, $allowedRoles, true)) {
            return response()->json([
                'message' => 'Unauthorized. Peran akun Anda tidak memiliki izin untuk mengakses endpoint ini.',
            ], 403);
        }

        return $next($request);
    }
}
