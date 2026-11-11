<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Memastikan pengguna yang terautentikasi memiliki status akun ACTIVE
     * sebelum mengakses fitur operasional sistem.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || strtoupper((string) $user->status) !== 'ACTIVE') {
            return response()->json([
                'message' => 'Akun Anda belum aktif atau sedang menunggu persetujuan Administrator.',
                'status' => $user ? $user->status : 'UNAUTHENTICATED',
            ], 403);
        }

        return $next($request);
    }
}
