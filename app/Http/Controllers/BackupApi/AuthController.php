<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegUser;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('username', 'password');

        // Cek kredensial dan status aktif
        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau password salah'
            ], 401);
        }

        $user = auth('api')->user();
        
        // Pengecekan status aktif
        if (property_exists($user, 'aktif') && !$user->aktif) {
            auth('api')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Akun tidak aktif'
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'user' => $user,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ]);
    }

    public function me()
    {
        $user = auth('api')->user();
        
        // Tambahkan pengecekan aktif di response
        return response()->json([
            'user' => $user,
            'is_active' => $user->aktif ?? true
        ]);
    }

    public function logout()
    {
        try {
            auth('api')->logout();
            
            return response()->json([
                'success' => true,
                'message' => 'Logout berhasil'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal logout'
            ], 500);
        }
    }

    public function refresh()
    {
        try {
            $newToken = auth('api')->refresh();
            $user = auth('api')->user();
            
            // Pengecekan status aktif saat refresh
            if (property_exists($user, 'aktif') && !$user->aktif) {
                auth('api')->logout();
                return response()->json([
                    'success' => false,
                    'message' => 'Akun tidak aktif'
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'token' => $newToken,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token'
            ], 401);
        }
    }
}