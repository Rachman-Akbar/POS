<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Token lama milik user yang sudah dinonaktifkan harus langsung kehilangan akses.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->is_active === false) {
            $user->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Akun dinonaktifkan. Hubungi administrator.',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
