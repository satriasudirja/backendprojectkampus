<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Models\SimpegUser;
use App\Services\SlideCaptchaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    protected $captchaService;
    
    public function __construct(SlideCaptchaService $captchaService)
    {
        $this->captchaService = $captchaService;
    }
    
    /**
     * Generate a new slide captcha for login
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateCaptcha()
    {
        $captcha = $this->captchaService->generateSlideCaptcha();
        
        if (isset($captcha['error'])) {
            return response()->json([
                'success' => false,
                'message' => $captcha['message']
            ], 500);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'captcha_id' => $captcha['captcha_id'],
                'background_url' => $captcha['background_url'],
                'slider_url' => $captcha['slider_url'],
                'slider_y' => $captcha['slider_y'],
                'captcha_url' => route('captcha.slide-captcha', ['id' => $captcha['captcha_id']])
            ]
        ]);
    }
    
    /**
     * Handle user login with captcha verification
     *
     * @param LoginRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        // Verify slide captcha first
        $captchaVerified = $this->captchaService->verifySlideCaptcha(
            $request->captcha_id, 
            $request->slider_position
        );
        
        if (!$captchaVerified) {
            return response()->json([
                'success' => false,
                'message' => 'CAPTCHA tidak valid atau sudah kadaluarsa'
            ], 422);
        }
        
        // Login menggunakan username (yang berasal dari NIP) dan password
        $credentials = [
            'username' => $request->nip, // NIP dari request akan dicari di kolom username
            'password' => $request->password
        ];

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json([
                'success' => false,
                'message' => 'NIP atau password salah'
            ], 401);
        }

        $user = Auth::user(); // Ini akan mengembalikan SimpegUser instance
        
        // Load relasi pegawai untuk mendapatkan data lengkap
        $user->load('pegawai.jabatanAkademik', 'pegawai.role');
        
        // Log login activity - gunakan pegawai_id dari user
        DB::table('simpeg_login_logs')->insert([
            'pegawai_id' => $user->pegawai_id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'logged_in_at' => now(),
        ]);

        return $this->respondWithToken($token, $user);
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     * @param  \App\Models\SimpegUser $user
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token, $user)
    {
        // Pastikan relasi pegawai sudah di-load
        if (!$user->relationLoaded('pegawai')) {
            $user->load('pegawai.jabatanAkademik', 'pegawai.role');
        }
        
        $pegawai = $user->pegawai;
        
        // Tentukan role berdasarkan flag is_admin di pegawai
        $roleName = $pegawai->is_admin ? 'Admin' : ($pegawai->role->nama ?? 'Pegawai');

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'pegawai_id' => $pegawai->id,
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'email' => $pegawai->email_pribadi,
                'username' => $user->username,
            ],
            'role' => $roleName,
            'jabatan' => $pegawai->jabatanAkademik->jabatan_akademik ?? '-',
        ]);
    }

    public function me()
    {
        $user = Auth::user(); // SimpegUser instance
        
        // Load relasi pegawai jika belum di-load
        if (!$user->relationLoaded('pegawai')) {
            $user->load('pegawai.jabatanAkademik', 'pegawai.role');
        }
        
        $pegawai = $user->pegawai;
        $roleName = $pegawai->is_admin ? 'Admin' : ($pegawai->role->nama ?? 'Pegawai');
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'pegawai_id' => $pegawai->id,
                    'nip' => $pegawai->nip,
                    'nama' => $pegawai->nama,
                    'email' => $pegawai->email_pribadi,
                    'username' => $user->username,
                ],
                'role' => $roleName,
                'jabatan' => $pegawai->jabatanAkademik->jabatan_akademik ?? '-',
            ]
        ]);
    }
    
    public function logout()
    {
        $user = Auth::user(); // SimpegUser instance
        
        if ($user) {
            // Gunakan pegawai_id dari user untuk mencari log
            $log_id = \DB::table('simpeg_login_logs')
                ->where('pegawai_id', $user->pegawai_id)
                ->whereNull('logged_out_at')
                ->orderBy('logged_in_at', 'desc')
                ->value('id');
        
            // Update waktu logout di login log
            if ($log_id) {
                DB::table('simpeg_login_logs')
                    ->where('id', $log_id)
                    ->update(['logged_out_at' => now()]);
            }
        }
        
        // Logout JWT
        Auth::logout();
    
        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout'
        ]);
    }
    
    public function refresh()
    {
        $user = Auth::user();
        $newToken = Auth::refresh();
        
        return $this->respondWithToken($newToken, $user);
    }

    public function getMenu()
    {
        $user = Auth::user(); // SimpegUser instance
        
        // Load relasi pegawai jika belum di-load
        if (!$user->relationLoaded('pegawai')) {
            $user->load('pegawai.jabatanAkademik.role');
        }
        
        $pegawai = $user->pegawai;
        $roleName = $pegawai->is_admin ? 'Admin' : ($pegawai->jabatanAkademik->role->nama ?? 'Pegawai');
        
        $menus = [];
        
        switch ($roleName) {
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