<?php

namespace App\Http\Controllers\BackupApi\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegUser;
use App\Models\SimpegUserRole;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // Ubah nama method dari index() menjadi dashboard()
    public function dashboard()
    {
        try {
            $userStats = [
                'total' => SimpegUser::count(),
                'by_role' => SimpegUser::with('role')
                                ->selectRaw('role_id, count(*) as count')
                                ->groupBy('role_id')
                                ->get()
                                ->mapWithKeys(function ($item) {
                                    return [$item->role->nama => $item->count];
                                }),
                'new_today' => SimpegUser::whereDate('created_at', Carbon::today())->count()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'user_statistics' => $userStats,
                    'system_stats' => [
                        'daily_visitors' => 125,
                        'active_sessions' => DB::table('sessions')->count()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat dashboard'
            ], 500);
        }
    }

    // Atau alternatif: pertahankan method index() dan sesuaikan route
    public function index()
    {
        // Isi method yang sama dengan dashboard()
        return $this->dashboard();
    }
}