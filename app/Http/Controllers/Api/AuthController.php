<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\SimpegPegawai;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('nip', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'NIP atau password salah'
            ], 401);
        }

        $user = Auth::user();
        $role = $user->jabatanAkademik->role;
        \DB::table('simpeg_login_logs')->insert([
            'pegawai_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'logged_in_at' => now(),
        ]);

        return $this->respondWithToken($token, $user, $role);
    }

    protected function respondWithToken($token, $user, $role)
    {
        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'nip' => $user->nip,
                'nama' => $user->nama,
                'email' => $user->email_pribadi,
            ],
            'role' => $role->nama,
            'jabatan' => $user->jabatanAkademik->jabatan_akademik,
        ]);
    }

    public function me()
    {
        $user = Auth::user();
        $role = $user->jabatanAkademik->role;
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'nip' => $user->nip,
                    'nama' => $user->nama,
                    'email' => $user->email_pribadi,
                ],
                'role' => $role->nama,
                'jabatan' => $user->jabatanAkademik->jabatan_akademik,
            ]
        ]);
    }
    public function logout()
    {
        $user = Auth::user();  // Mendapatkan user yang sedang login
        $log_id = \DB::table('simpeg_login_logs')
            ->where('pegawai_id', $user->id)
            ->whereNull('logged_out_at')  // Pastikan belum ada waktu logout
            ->value('id');
    
        // Update waktu logout di login log
        \DB::table('simpeg_login_logs')
            ->where('id', $log_id)
            ->update(['logged_out_at' => now()]);
    
        // Logout JWT
        Auth::logout();
    
        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout'
        ]);
    }
    

    public function refresh()
    {
        return $this->respondWithToken(Auth::refresh());
    }

    public function getMenu()
    {
        $user = Auth::user();
        $role = $user->jabatanAkademik->role;
        
        $menus = [];
        
        switch ($role->nama) {
            case 'Admin':
                $menus = [
                    ['name' => 'Dashboard', 'path' => '/admin/dashboard', 'icon' => 'mdi-view-dashboard'],
                    ['name' => 'Manajemen User', 'path' => '/admin/users', 'icon' => 'mdi-account-group'],
                    ['name' => 'Manajemen Role', 'path' => '/admin/roles', 'icon' => 'mdi-shield-account'],
                ];
                break;
            case 'Dosen':
                $menus = [
                    ['name' => 'Dashboard', 'path' => '/dosen/dashboard', 'icon' => 'mdi-view-dashboard'],
                    ['name' => 'Jadwal Mengajar', 'path' => '/dosen/schedule', 'icon' => 'mdi-calendar-clock'],
                    ['name' => 'Materi Ajar', 'path' => '/dosen/materials', 'icon' => 'mdi-book-education'],
                ];
                break;
            case 'Dosen Praktisi/Industri':
                $menus = [
                    ['name' => 'Dashboard', 'path' => '/dosen-praktisi/dashboard', 'icon' => 'mdi-view-dashboard'],
                    ['name' => 'Jadwal', 'path' => '/dosen-praktisi/schedule', 'icon' => 'mdi-calendar-clock'],
                ];
                break;
            case 'Tenaga Kependidikan':
                $menus = [
                    ['name' => 'Dashboard', 'path' => '/tenaga-kependidikan/dashboard', 'icon' => 'mdi-view-dashboard'],
                    ['name' => 'Laporan', 'path' => '/tenaga-kependidikan/reports', 'icon' => 'mdi-file-document'],
                ];
                break;
        }
        
        return response()->json([
            'success' => true,
            'data' => $menus
        ]);
    }
}