<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\SimpegUsersRole;
use Illuminate\Http\Request;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param string ...$roles
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }

            // Cek jika user adalah admin berdasarkan flag 'is_admin'
            if ($user->is_admin && in_array('Admin', $roles)) {
                return $next($request);
            }
            
            // Jika bukan admin, cek role berdasarkan jabatan akademik
            $userRole = $user->jabatanAkademik->role->nama ?? null;

            if (!in_array($userRole, $roles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses yang diizinkan untuk fitur ini.'
                ], 403);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token tidak valid atau telah kedaluwarsa.'
            ], 401);
        }

        return $next($request);
    }
}
