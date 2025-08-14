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

            // 1. Ambil data pegawai yang berelasi dengan user
            $pegawai = $user->pegawai;

            // Tambahkan pengecekan jika user tidak memiliki data pegawai terkait
            if (!$pegawai) {
                return response()->json(['success' => false, 'message' => 'Data pegawai untuk user ini tidak ditemukan.'], 403);
            }

            // 2. PERBAIKAN: Cek 'is_admin' dari data pegawai, bukan user
            // Asumsi kolom 'is_admin' ada di tabel simpeg_pegawai
            if ($pegawai->is_admin && in_array('Admin', $roles)) {
                return $next($request);
            }
            
            // 3. PERBAIKAN: Ambil role langsung dari relasi pegawai->role
            // Asumsi nama kolom di tabel role adalah 'nama' atau 'nama_role'. Sesuaikan jika berbeda.
            $userRole = $pegawai->role->nama ?? null;

            if (!in_array($userRole, $roles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses yang diizinkan untuk fitur ini. Role Anda: ' . ($userRole ?? 'Tidak Ada'),
                ], 403);
            }

        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return response()->json(['success' => false, 'message' => 'Token telah kedaluwarsa.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['success' => false, 'message' => 'Token tidak valid.'], 401);
        } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
            return response()->json(['success' => false, 'message' => 'Token tidak ditemukan.'], 401);
        }

        return $next($request);
    }
}
