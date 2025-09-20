<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegBerita;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SimpegBeritaPegawaiController extends Controller
{
    /**
     * Display a listing of the resource berdasarkan unit kerja pegawai.
     */
    public function index(Request $request)
    {
        try {
            // Ambil data pegawai yang sedang login
            $pegawai = Auth::user()->pegawai;
            
            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            $berita = SimpegBerita::query();

            // Filter berita berdasarkan unit kerja pegawai
            $berita->whereRaw("unit_kerja_id::jsonb @> ?::jsonb", [json_encode([$pegawai->unit_kerja_id])]);

            // Filter berdasarkan judul jika ada parameter
            if ($request->has('judul') && !empty($request->judul)) {
                $berita->where('judul', 'like', '%' . $request->judul . '%');
            }

            // Filter berdasarkan tanggal posting
            if ($request->has('tgl_posting_from') && !empty($request->tgl_posting_from)) {
                $berita->where('tgl_posting', '>=', $request->tgl_posting_from);
            }
            
            if ($request->has('tgl_posting_to') && !empty($request->tgl_posting_to)) {
                $berita->where('tgl_posting', '<=', $request->tgl_posting_to);
            }

            // Filter berdasarkan prioritas
            if ($request->has('prioritas') && $request->prioritas !== '') {
                $berita->where('prioritas', $request->prioritas);
            }

            // Filter berdasarkan jabatan akademik
            if ($request->has('jabatan_akademik_id') && !empty($request->jabatan_akademik_id)) {
                $berita->whereHas('jabatanAkademik', function($query) use ($request) {
                    $query->where('jabatan_akademik_id', $request->jabatan_akademik_id);
                });
            }

            // Hanya tampilkan berita yang belum expired dan sudah dipublish
            $berita->where(function($query) {
                $query->whereNull('tgl_expired')
                      ->orWhere('tgl_expired', '>=', now());
            })
            ->where('tgl_posting', '<=', now())
            ->orderBy('prioritas', 'desc')
            ->orderBy('tgl_posting', 'desc');

            // Load relasi dengan error handling
            try {
                $berita->with(['jabatanAkademik']);
            } catch (\Exception $e) {
                // Jika relasi error, lanjutkan tanpa relasi
            }

            $berita = $berita->paginate(10);

            // Tambahkan URL untuk detail
            $prefix = $request->segment(2);
            $berita->getCollection()->transform(function ($item) use ($prefix) {
                $item->detail_url = url("/api/{$prefix}/berita-pegawai/" . $item->id);
                
                // Format tanggal untuk tampilan
                $item->tgl_posting_formatted = date('d/m/Y', strtotime($item->tgl_posting));
                $item->tgl_expired_formatted = $item->tgl_expired ? date('d/m/Y', strtotime($item->tgl_expired)) : '-';
                $item->prioritas_text = $item->prioritas ? 'Tinggi' : 'Normal';
                
                // URL gambar jika ada
                if ($item->gambar_berita) {
                    $item->gambar_berita_url = asset('storage/' . $item->gambar_berita);
                }
                
                // URL file jika ada  
                if ($item->file_berita) {
                    $item->file_berita_url = asset('storage/' . $item->file_berita);
                }
                
                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => $berita,
                'pegawai_info' => [
                    'nama' => $pegawai->nama ?? 'N/A',
                    'unit_kerja' => $pegawai->unitKerja->nama ?? 'Unit Kerja Tidak Ditemukan'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'debug' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Display the specified resource (detail berita).
     */
    public function show(Request $request, $id)
    {
        try {
            // Ambil data pegawai yang sedang login
            $pegawai = Auth::user()->pegawai;
            
            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            // Ambil berita tanpa relasi dulu untuk menghindari error
            $berita = SimpegBerita::find($id);

            if (!$berita) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Berita tidak ditemukan'
                ], 404);
            }

            // Cek apakah berita sesuai dengan unit kerja pegawai
            $unitKerjaIds = [];
            
            if ($berita->unit_kerja_id) {
                if (is_string($berita->unit_kerja_id)) {
                    try {
                        $unitKerjaIds = json_decode($berita->unit_kerja_id, true);
                        if (!is_array($unitKerjaIds)) {
                            $unitKerjaIds = [$berita->unit_kerja_id];
                        }
                    } catch (\Exception $e) {
                        $unitKerjaIds = [$berita->unit_kerja_id];
                    }
                } elseif (is_array($berita->unit_kerja_id)) {
                    $unitKerjaIds = $berita->unit_kerja_id;
                } else {
                    $unitKerjaIds = [$berita->unit_kerja_id];
                }
            }
            
            if (!empty($unitKerjaIds) && !in_array($pegawai->unit_kerja_id, $unitKerjaIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk melihat berita ini'
                ], 403);
            }

            // Load relasi secara terpisah untuk menghindari error
            try {
                $berita->load('jabatanFungsional');
            } catch (\Exception $e) {
                // Jika relasi jabatanAkademik error, skip
            }

            // Format data untuk response
            $berita->tgl_posting_formatted = date('d/m/Y H:i', strtotime($berita->tgl_posting));
            $berita->tgl_expired_formatted = $berita->tgl_expired ? date('d/m/Y H:i', strtotime($berita->tgl_expired)) : null;
            $berita->prioritas_text = $berita->prioritas ? 'Tinggi' : 'Normal';

            // URL gambar jika ada
            if ($berita->gambar_berita) {
                $berita->gambar_berita_url = asset('storage/' . $berita->gambar_berita);
            }
            
            // URL file jika ada  
            if ($berita->file_berita) {
                $berita->file_berita_url = asset('storage/' . $berita->file_berita);
            }

            return response()->json([
                'success' => true,
                'data' => $berita,
                'pegawai_info' => [
                    'nama' => $pegawai->nama ?? 'N/A',
                    'unit_kerja' => $pegawai->unitKerja->nama ?? 'Unit Kerja Tidak Ditemukan'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'debug' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Get daftar unit kerja untuk filter
     */
    public function getUnitKerja()
    {
        try {
            // Sesuaikan dengan kolom yang sebenarnya ada di tabel simpeg_unit_kerja
            $unitKerja = SimpegUnitKerja::select('id', 'nama_unit_kerja as nama', 'kode_unit_kerja as kode')
                                       ->orderBy('nama_unit_kerja')
                                       ->get();

            return response()->json([
                'success' => true,
                'data' => $unitKerja
            ]);
        } catch (\Exception $e) {
            // Jika masih error, coba dengan struktur tabel yang berbeda
            try {
                $unitKerja = SimpegUnitKerja::select('id', 'nama_unit as nama', 'kode_unit as kode')
                                           ->orderBy('id')
                                           ->get();
                
                return response()->json([
                    'success' => true,
                    'data' => $unitKerja
                ]);
            } catch (\Exception $e2) {
                // Fallback - ambil semua kolom untuk debugging
                $unitKerja = SimpegUnitKerja::all();
                
                return response()->json([
                    'success' => true,
                    'data' => $unitKerja,
                    'debug_message' => 'Mengambil semua kolom karena struktur tabel tidak sesuai'
                ]);
            }
        }
    }

    /**
     * Get statistik berita untuk dashboard
     */
    public function getStatistik()
    {
        try {
            // Ambil data pegawai yang sedang login
            $pegawai = Auth::user()->pegawai;
            
            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data pegawai tidak ditemukan'
                ], 404);
            }

            // Hitung berita berdasarkan unit kerja pegawai
            $totalBerita = SimpegBerita::whereRaw("unit_kerja_id::jsonb @> ?::jsonb", [json_encode([$pegawai->unit_kerja_id])])
                                      ->count();

            $beritaPrioritas = SimpegBerita::whereRaw("unit_kerja_id::jsonb @> ?::jsonb", [json_encode([$pegawai->unit_kerja_id])])
                                          ->where('prioritas', true)
                                          ->count();

            $beritaAktif = SimpegBerita::whereRaw("unit_kerja_id::jsonb @> ?::jsonb", [json_encode([$pegawai->unit_kerja_id])])
                                      ->where(function($query) {
                                          $query->whereNull('tgl_expired')
                                                ->orWhere('tgl_expired', '>=', now());
                                      })
                                      ->where('tgl_posting', '<=', now())
                                      ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_berita' => $totalBerita,
                    'berita_prioritas' => $beritaPrioritas,
                    'berita_aktif' => $beritaAktif,
                    'unit_kerja' => $pegawai->unitKerja->nama ?? 'Unit Kerja Tidak Ditemukan'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
                'debug' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Ambil data pegawai yang sedang login
     * Sesuaikan dengan sistem autentikasi yang digunakan
     */
    private function pegawaiHasAccessToBerita($pegawai, $berita): bool
    {
        $targetUnitIds = json_decode($berita->unit_kerja_id, true) ?? [];

        // Jika berita untuk semua unit, beri akses
        if (empty($targetUnitIds) || in_array('semua', $targetUnitIds)) {
            return true;
        }

        // Jika unit kerja pegawai ada di dalam daftar target, beri akses
        if (in_array($pegawai->unit_kerja_id, $targetUnitIds)) {
            return true;
        }

        // Jika jabatan akademik pegawai ada di dalam daftar target, beri akses
        if ($pegawai->jabatan_akademik_id && $berita->jabatanAkademik()->where('jabatan_akademik_id', $pegawai->jabatan_akademik_id)->exists()) {
            return true;
        }

        // Jika tidak memenuhi semua syarat, tolak akses
        return false;
    }
}