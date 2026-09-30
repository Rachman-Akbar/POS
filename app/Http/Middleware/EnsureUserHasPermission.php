<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Izinkan request hanya bila user punya seluruh permission yang diminta.
     *
     * Pemeriksaan dilakukan di backend, jadi menyembunyikan menu di frontend
     * tidak pernah menjadi satu-satunya lapisan proteksi.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Belum terautentikasi.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->is_active === false) {
            return response()->json([
                'message' => 'Akun dinonaktifkan. Hubungi administrator.',
            ], Response::HTTP_FORBIDDEN);
        }

        $missing = collect($permissions)
            ->reject(fn (string $permission): bool => $user->hasPermission($permission))
            ->values()
            ->all();

        if ($missing === []) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Anda tidak memiliki hak akses untuk aksi ini.',
            'missing_permission' => $missing[0],
        ], Response::HTTP_FORBIDDEN);
    }
}
