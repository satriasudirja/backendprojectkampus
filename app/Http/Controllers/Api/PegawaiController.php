<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegStatusAktif;
use App\Models\HubunganKerja;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

class PegawaiController extends Controller
{
       public function index(Request $request)
    {
        $query = SimpegPegawai::with([
            'unitKerja',
            'statusAktif',
            'dataHubunganKerja.hubunganKerja',
            'dataJabatanFungsional.jabatanFungsional.jabatanAkademik'
        ]);

        // Filtering
        if ($request->has('nip') && !empty($request->nip)) {
            $query->where('nip', 'like', '%' . $request->nip . '%');
        }

        if ($request->has('nidn') && !empty($request->nidn)) {
            $query->where('nidn', 'like', '%' . $request->nidn . '%');
        }

        if ($request->has('nuptk') && !empty($request->nuptk)) {
            $query->where('nuptk', 'like', '%' . $request->nuptk . '%');
        }

        if ($request->has('nama') && !empty($request->nama)) {
            $query->where('nama', 'like', '%' . $request->nama . '%');
        }

        if ($request->has('unit_kerja_id') && !empty($request->unit_kerja_id)) {
            $query->where('unit_kerja_id', $request->unit_kerja_id);
        }

        if ($request->has('status_aktif_id') && !empty($request->status_aktif_id)) {
            $query->where('status_aktif_id', $request->status_aktif_id);
        }

        if ($request->has('hubungan_kerja_id') && !empty($request->hubungan_kerja_id)) {
            $query->whereHas('dataHubunganKerja', function ($q) use ($request) {
                $q->where('hubungan_kerja_id', $request->hubungan_kerja_id);
            });
        }

        if ($request->has('jabatan_fungsional_id') && !empty($request->jabatan_fungsional_id)) {
            $query->whereHas('dataJabatanFungsional', function ($q) use ($request) {
                $q->where('jabatan_fungsional_id', $request->jabatan_fungsional_id);
            });
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('nip', 'like', '%' . $search . '%')
                  ->orWhere('nidn', 'like', '%' . $search . '%')
                  ->orWhere('nuptk', 'like', '%' . $search . '%');
            });
        }

        // Pagination
        $perPage = $request->has('per_page') ? (int)$request->per_page : 10;
        $pegawai = $query->orderBy('nama', 'asc')->paginate($perPage);

        // Get prefix from URL
        $prefix = $request->segment(2);

        // Transform data to simplified table view
        $pegawaiData = $pegawai->getCollection()->map(function ($item) use ($prefix) {
            $terhubung_sister = false; // Placeholder, replace with actual logic
            
            // Debug the unitKerja relationship
            $unit_kerja_nama = null;
            if ($item->unitKerja) {
                $unit_kerja_nama = $item->unitKerja->nama_unit;
            } elseif ($item->unit_kerja_id) {
                // If relationship failed but ID exists, try to get directly
                $unitKerja = SimpegUnitKerja::find($item->unit_kerja_id);
                $unit_kerja_nama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $item->unit_kerja_id;
            } else {
                $unit_kerja_nama = 'Tidak Ada';
            }
            
            return [
                'id' => $item->id, // Keep the ID for action purposes
                'nip' => $item->nip,
                'nidn' => $item->nidn,
                'nuptk' => $item->nuptk ?? '',
                'nama_pegawai' => $item->nama,
                'unit_kerja' => $unit_kerja_nama,
                'status' => $item->statusAktif ? $item->statusAktif->kode : '-',
                'terhubung_sister' => $terhubung_sister ? 'Ya' : '-',
                'aksi' => [
                    'detail_url' => url("/api/{$prefix}/pegawai/" . $item->id),
                    'delete_url' => url("/api/{$prefix}/pegawai/" . $item->id),
                    'update_status_url' => url("/api/{$prefix}/pegawai/update-status/" . $item->id),
                    'riwayat' => [
                        'unit_kerja' => url("/api/{$prefix}/pegawai/riwayat-unit-kerja/" . $item->id),
                        'pendidikan' => url("/api/{$prefix}/pegawai/riwayat-pendidikan/" . $item->id),
                        'pangkat' => url("/api/{$prefix}/pegawai/riwayat-pangkat/" . $item->id),
                        'fungsional' => url("/api/{$prefix}/pegawai/riwayat-fungsional/" . $item->id),
                        'jenjang_fungsional' => url("/api/{$prefix}/pegawai/riwayat-jenjang-fungsional/" . $item->id),
                        'jabatan_struktural' => url("/api/{$prefix}/pegawai/riwayat-jabatan-struktural/" . $item->id),
                        'hubungan_kerja' => url("/api/{$prefix}/pegawai/riwayat-hubungan-kerja/" . $item->id),
                        'rekap_kehadiran' => url("/api/{$prefix}/pegawai/rekap-kehadiran/" . $item->id)
                    ]
                ]
            ];
        });

        // Create a new paginator with the transformed data
        $paginationData = $pegawai->toArray();
        unset($paginationData['data']);
        
        // Get filter options for dropdowns
        $unitKerja = SimpegUnitKerja::select('id', 'nama_unit')->orderBy('nama_unit', 'asc')->get();
        $statusAktif = SimpegStatusAktif::select('id', 'nama_status_aktif')->orderBy('nama_status_aktif', 'asc')->get();
        $hubunganKerja = HubunganKerja::select('id', 'nama_hub_kerja')->orderBy('nama_hub_kerja', 'asc')->get();
        
        return response()->json([
            'success' => true,
            'data' => array_merge(['data' => $pegawaiData], $paginationData),
            'filters' => [
                'unit_kerja' => $unitKerja,
                'status_aktif' => $statusAktif,
                'hubungan_kerja' => $hubunganKerja,
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'table_columns' => [
                ['field' => 'nip', 'label' => 'NIP'],
                ['field' => 'nidn', 'label' => 'NIDN'],
                ['field' => 'nuptk', 'label' => 'NUPTK'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai'],
                ['field' => 'unit_kerja', 'label' => 'Unit Kerja'],
                ['field' => 'status', 'label' => 'Status'],
                ['field' => 'terhubung_sister', 'label' => 'Terhubung Sister'],
                ['field' => 'aksi', 'label' => 'Aksi'],
            ],
            'tambah_pegawai_url' => url("/api/{$prefix}/pegawai/create")
        ]);
    }



    public function show(Request $request, $id)
    {
        $pegawai = SimpegPegawai::with([
            'unitKerja',
            'statusAktif',
            'dataHubunganKerja.hubunganKerja',
            'dataJabatanFungsional.jabatanFungsional.jabatanAkademik',
            'dataPendidikanFormal',
            'dataPangkat',
            'dataJabatanAkademik',
            'dataJabatanFungsional',
            'dataJabatanStruktural',
            'absensiRecord'
        ])->find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $pegawai,
            'update_url' => url("/api/{$prefix}/pegawai/" . $pegawai->id),
            'delete_url' => url("/api/{$prefix}/pegawai/" . $pegawai->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nip' => 'required|string|max:50|unique:simpeg_pegawai,nip',
            'nidn' => 'nullable|string|max:50',
            'nuptk' => 'nullable|string|max:50',
            'nama' => 'required|string|max:255',
            'unit_kerja_id' => 'required|exists:simpeg_unit_kerja,id',
            'status_aktif_id' => 'required|exists:simpeg_status_aktif,id',
            // Add other required fields
        ]);

        DB::beginTransaction();
        try {
            // Create pegawai
            $pegawai = SimpegPegawai::create([
                'nip' => $request->nip,
                'nidn' => $request->nidn,
                'nuptk' => $request->nuptk,
                'nama' => $request->nama,
                'unit_kerja_id' => $request->unit_kerja_id,
                'status_aktif_id' => $request->status_aktif_id,
                // Add other fields
            ]);

            // If hubungan_kerja_id is provided, create relation in simpeg_data_hubungan_kerja
            if ($request->has('hubungan_kerja_id') && !empty($request->hubungan_kerja_id)) {
                $pegawai->dataHubunganKerja()->create([
                    'hubungan_kerja_id' => $request->hubungan_kerja_id,
                    'tanggal_mulai' => now(),
                    // Add other fields
                ]);
            }

            // If jabatan_fungsional_id is provided, create relation in simpeg_data_jabatan_fungsional
            if ($request->has('jabatan_fungsional_id') && !empty($request->jabatan_fungsional_id)) {
                $pegawai->dataJabatanFungsional()->create([
                    'jabatan_fungsional_id' => $request->jabatan_fungsional_id,
                    'tanggal_mulai' => now(),
                    // Add other fields
                ]);
            }

            ActivityLogger::log('create', $pegawai, $pegawai->toArray());
            
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $pegawai,
                'message' => 'Pegawai berhasil ditambahkan'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan pegawai: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $request->validate([
            'nip' => 'required|string|max:50|unique:simpeg_pegawai,nip,'.$id,
            'nidn' => 'nullable|string|max:50',
            'nuptk' => 'nullable|string|max:50',
            'nama' => 'required|string|max:255',
            'unit_kerja_id' => 'required|exists:simpeg_unit_kerja,id',
            'status_aktif_id' => 'required|exists:simpeg_status_aktif,id',
            // Add other required fields
        ]);

        $old = $pegawai->getOriginal();

        DB::beginTransaction();
        try {
            $pegawai->update([
                'nip' => $request->nip,
                'nidn' => $request->nidn,
                'nuptk' => $request->nuptk,
                'nama' => $request->nama,
                'unit_kerja_id' => $request->unit_kerja_id,
                'status_aktif_id' => $request->status_aktif_id,
                // Add other fields
            ]);

            // Update relations if needed
            if ($request->has('hubungan_kerja_id') && !empty($request->hubungan_kerja_id)) {
                // End current relationship
                $pegawai->dataHubunganKerja()
                    ->whereNull('tanggal_selesai')
                    ->update(['tanggal_selesai' => now()]);
                
                // Create new relationship
                $pegawai->dataHubunganKerja()->create([
                    'hubungan_kerja_id' => $request->hubungan_kerja_id,
                    'tanggal_mulai' => now(),
                    // Add other fields
                ]);
            }

            // Similar pattern for jabatan_fungsional
            if ($request->has('jabatan_fungsional_id') && !empty($request->jabatan_fungsional_id)) {
                $pegawai->dataJabatanFungsional()
                    ->whereNull('tanggal_selesai')
                    ->update(['tanggal_selesai' => now()]);
                
                $pegawai->dataJabatanFungsional()->create([
                    'jabatan_fungsional_id' => $request->jabatan_fungsional_id,
                    'tanggal_mulai' => now(),
                    // Add other fields
                ]);
            }

            $changes = array_diff_assoc($pegawai->toArray(), $old);
            ActivityLogger::log('update', $pegawai, $changes);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $pegawai,
                'message' => 'Pegawai berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui pegawai: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'pegawai_ids' => 'required|array',
            'pegawai_ids.*' => 'exists:simpeg_pegawai,id',
            'status_aktif_id' => 'required|exists:simpeg_status_aktif,id'
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->pegawai_ids as $id) {
                $pegawai = SimpegPegawai::find($id);
                if (!$pegawai) continue;
                
                $old = $pegawai->getOriginal();
                
                $pegawai->update([
                    'status_aktif_id' => $request->status_aktif_id
                ]);
                
                $changes = array_diff_assoc($pegawai->toArray(), $old);
                ActivityLogger::log('update', $pegawai, $changes);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Status pegawai berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status pegawai: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        $request->validate([
            'pegawai_ids' => 'required|array',
            'pegawai_ids.*' => 'exists:simpeg_pegawai,id',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->pegawai_ids as $id) {
                $pegawai = SimpegPegawai::find($id);
                if (!$pegawai) continue;
                
                $pegawaiData = $pegawai->toArray();
                
                $pegawai->delete(); // Using SoftDeletes
                
                ActivityLogger::log('delete', $pegawai, $pegawaiData);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Pegawai berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pegawai: ' . $e->getMessage()
            ], 500);
        }
    }

    public function riwayatUnitKerja($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $riwayat = $pegawai->riwayatUnitKerja()->with('unitKerja')->orderBy('tanggal_mulai', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'riwayat' => $riwayat
            ]
        ]);
    }

    public function riwayatPendidikan($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $riwayat = $pegawai->dataPendidikanFormal()->orderBy('tahun_lulus', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'riwayat' => $riwayat
            ]
        ]);
    }

    public function riwayatPangkat($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $riwayat = $pegawai->dataPangkat()->orderBy('tanggal_mulai', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'riwayat' => $riwayat
            ]
        ]);
    }

    public function riwayatFungsional($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $riwayat = $pegawai->dataJabatanAkademik()->orderBy('tanggal_mulai', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'riwayat' => $riwayat
            ]
        ]);
    }

    public function riwayatJenjangFungsional($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $riwayat = $pegawai->dataJabatanFungsional()->with('jabatanFungsional.jabatanAkademik')
            ->orderBy('tanggal_mulai', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'riwayat' => $riwayat
            ]
        ]);
    }

    public function riwayatJabatanStruktural($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $riwayat = $pegawai->dataJabatanStruktural()->orderBy('tanggal_mulai', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'riwayat' => $riwayat
            ]
        ]);
    }

    public function riwayatHubunganKerja($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $riwayat = $pegawai->dataHubunganKerja()->with('hubunganKerja')
            ->orderBy('tanggal_mulai', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'riwayat' => $riwayat
            ]
        ]);
    }

    public function rekapKehadiran($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $rekap = $pegawai->absensiRecord()
            ->select(
                DB::raw('YEAR(tanggal) as tahun'),
                DB::raw('MONTH(tanggal) as bulan'),
                DB::raw('COUNT(*) as total_kehadiran'),
                DB::raw('SUM(CASE WHEN status = "hadir" THEN 1 ELSE 0 END) as hadir'),
                DB::raw('SUM(CASE WHEN status = "sakit" THEN 1 ELSE 0 END) as sakit'),
                DB::raw('SUM(CASE WHEN status = "izin" THEN 1 ELSE 0 END) as izin'),
                DB::raw('SUM(CASE WHEN status = "cuti" THEN 1 ELSE 0 END) as cuti'),
                DB::raw('SUM(CASE WHEN status = "alpa" THEN 1 ELSE 0 END) as alpa')
            )
            ->groupBy('tahun', 'bulan')
            ->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'rekap' => $rekap
            ]
        ]);
    }
}