<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataKeluargaPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SimpegDataKeluargaController extends Controller
{
    // Get overview of all family data for logged in pegawai
    public function index(Request $request)
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $statusPengajuan = $request->status_pengajuan;
        $familyType = $request->family_type;

        $query = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id);

        // Filter by search (nama, nama_pasangan)
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nama', 'like', '%'.$search.'%')
                  ->orWhere('nama_pasangan', 'like', '%'.$search.'%')
                  ->orWhere('status_orangtua', 'like', '%'.$search.'%');
            });
        }

        // Filter by status pengajuan
        if ($statusPengajuan && $statusPengajuan != 'semua') {
            $query->where('status_pengajuan', $statusPengajuan);
        }

        // Filter by family type
        if ($familyType && $familyType != 'semua') {
            switch ($familyType) {
                case 'anak':
                    $query->whereNotNull('anak_ke');
                    break;
                case 'pasangan':
                    $query->whereNotNull('nama_pasangan');
                    break;
                case 'orangtua':
                    $query->whereNotNull('status_orangtua');
                    break;
            }
        }

        $dataKeluarga = $query->orderBy('created_at', 'desc')
                             ->paginate($perPage);

        // Get summary statistics
        $summary = $this->getFamilySummary($pegawai->id);

        return response()->json([
            'success' => true,
            'pegawai' => [
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
                'status' => $pegawai->statusAktif->nama_status_aktif ?? '-',
                'jab_akademik' => $pegawai->jabatanAkademik->nama_jabatan ?? '-',
                'jab_fungsional' => $pegawai->dataJabatanFungsional->first()->jabatanFungsional->nama_jabatan ?? '-',
                'jab_struktural' => $pegawai->dataJabatanStruktural->first()->jabatanStruktural->nama_jabatan ?? '-',
                'pendidikan' => $pegawai->dataPendidikan->first()->jenjangPendidikan->nama_jenjang ?? '-'
            ],
            'summary' => $summary,
            'data' => $dataKeluarga->map(function ($item) {
                return $this->formatDataKeluarga($item);
            }),
            'pagination' => [
                'current_page' => $dataKeluarga->currentPage(),
                'per_page' => $dataKeluarga->perPage(),
                'total' => $dataKeluarga->total(),
                'last_page' => $dataKeluarga->lastPage()
            ]
        ]);
    }

    // Get dashboard summary
    public function dashboard()
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        $summary = $this->getFamilySummary($pegawai->id);
        
        // Get recent submissions
        $recentSubmissions = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', '!=', 'draft')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return $this->formatDataKeluarga($item);
            });

        // Get pending approvals
        $pendingApprovals = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'diajukan')
            ->count();

        return response()->json([
            'success' => true,
            'pegawai' => [
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
                'status' => $pegawai->statusAktif->nama_status_aktif ?? '-',
                'jab_akademik' => $pegawai->jabatanAkademik->nama_jabatan ?? '-',
                'jab_fungsional' => $pegawai->dataJabatanFungsional->first()->jabatanFungsional->nama_jabatan ?? '-',
                'jab_struktural' => $pegawai->dataJabatanStruktural->first()->jabatanStruktural->nama_jabatan ?? '-',
                'pendidikan' => $pegawai->dataPendidikan->first()->jenjangPendidikan->nama_jenjang ?? '-'
            ],
            'summary' => $summary,
            'recent_submissions' => $recentSubmissions,
            'pending_approvals' => $pendingApprovals
        ]);
    }

    // Get detail of specific family member
    public function show($id)
    {
        $pegawai = Auth::user()->pegawai;

        $dataKeluarga = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataKeluarga) {
            return response()->json([
                'success' => false,
                'message' => 'Data keluarga tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'pegawai' => [
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
                'status' => $pegawai->statusAktif->nama_status_aktif ?? '-',
                'jab_akademik' => $pegawai->jabatanAkademik->nama_jabatan ?? '-',
                'jab_fungsional' => $pegawai->dataJabatanFungsional->first()->jabatanFungsional->nama_jabatan ?? '-',
                'jab_struktural' => $pegawai->dataJabatanStruktural->first()->jabatanStruktural->nama_jabatan ?? '-',
                'pendidikan' => $pegawai->dataPendidikan->first()->jenjangPendidikan->nama_jenjang ?? '-'
            ],
            'data' => $this->formatDataKeluarga($dataKeluarga)
        ]);
    }

    // Delete any family data
    public function destroy($id)
    {
        $pegawai = Auth::user()->pegawai;

        $dataKeluarga = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->find($id);

        if (!$dataKeluarga) {
            return response()->json([
                'success' => false,
                'message' => 'Data keluarga tidak ditemukan'
            ], 404);
        }

        // Delete associated files
        if ($dataKeluarga->file_akte) {
            \Storage::delete('public/pegawai/keluarga/akte/'.$dataKeluarga->file_akte);
        }
        if ($dataKeluarga->file_karpeg_pasangan) {
            \Storage::delete('public/pegawai/keluarga/karpeg/'.$dataKeluarga->file_karpeg_pasangan);
        }
        if ($dataKeluarga->kartu_nikah) {
            \Storage::delete('public/pegawai/keluarga/nikah/'.$dataKeluarga->kartu_nikah);
        }

        $familyType = $this->getFamilyType($dataKeluarga);
        $oldData = $dataKeluarga->toArray();
        $dataKeluarga->delete();

        ActivityLogger::log('delete', $dataKeluarga, $oldData);

        return response()->json([
            'success' => true,
            'message' => "Data {$familyType} berhasil dihapus"
        ]);
    }

    // Batch update status
    public function batchUpdateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $pegawai = Auth::user()->pegawai;

        $updateData = [
            'status_pengajuan' => $request->status_pengajuan,
            'keterangan' => $request->keterangan
        ];

        // Set timestamp based on status
        switch ($request->status_pengajuan) {
            case 'diajukan':
                $updateData['tgl_diajukan'] = now();
                break;
            case 'disetujui':
                $updateData['tgl_disetujui'] = now();
                break;
            case 'ditolak':
                $updateData['tgl_ditolak'] = now();
                break;
        }

        SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui'
        ]);
    }

    // Batch delete
    public function batchDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $pegawai = Auth::user()->pegawai;

        $dataKeluarga = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->get();

        // Delete associated files
        foreach ($dataKeluarga as $item) {
            if ($item->file_akte) {
                \Storage::delete('public/pegawai/keluarga/akte/'.$item->file_akte);
            }
            if ($item->file_karpeg_pasangan) {
                \Storage::delete('public/pegawai/keluarga/karpeg/'.$item->file_karpeg_pasangan);
            }
            if ($item->kartu_nikah) {
                \Storage::delete('public/pegawai/keluarga/nikah/'.$item->kartu_nikah);
            }
            ActivityLogger::log('delete', $item, $item->toArray());
        }

        SimpegDataKeluargaPegawai::where('pegawai_id', $pegawai->id)
            ->whereIn('id', $request->ids)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data keluarga berhasil dihapus'
        ]);
    }

    // Get filter options
    public function getFilterOptions()
    {
        $pegawai = Auth::user()->pegawai;

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Data pegawai tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'filter_options' => [
                'family_type' => [
                    ['value' => 'semua', 'label' => 'Semua Tipe'],
                    ['value' => 'anak', 'label' => 'Anak'],
                    ['value' => 'pasangan', 'label' => 'Pasangan'],
                    ['value' => 'orangtua', 'label' => 'Orang Tua']
                ],
                'status_pengajuan' => [
                    ['value' => 'semua', 'label' => 'Semua Status'],
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'diajukan', 'label' => 'Diajukan'],
                    ['value' => 'disetujui', 'label' => 'Disetujui'],
                    ['value' => 'ditolak', 'label' => 'Ditolak']
                ]
            ]
        ]);
    }

    // Helper method to get family summary
    private function getFamilySummary($pegawaiId)
    {
        $totalAnak = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawaiId)
            ->whereNotNull('anak_ke')
            ->count();

        $totalPasangan = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawaiId)
            ->whereNotNull('nama_pasangan')
            ->count();

        $totalOrangTua = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawaiId)
            ->whereNotNull('status_orangtua')
            ->count();

        $statusCounts = SimpegDataKeluargaPegawai::where('pegawai_id', $pegawaiId)
            ->selectRaw('status_pengajuan, count(*) as count')
            ->groupBy('status_pengajuan')
            ->pluck('count', 'status_pengajuan')
            ->toArray();

        return [
            'total_family_members' => $totalAnak + $totalPasangan + $totalOrangTua,
            'total_anak' => $totalAnak,
            'total_pasangan' => $totalPasangan,
            'total_orangtua' => $totalOrangTua,
            'status_counts' => [
                'draft' => $statusCounts['draft'] ?? 0,
                'diajukan' => $statusCounts['diajukan'] ?? 0,
                'disetujui' => $statusCounts['disetujui'] ?? 0,
                'ditolak' => $statusCounts['ditolak'] ?? 0
            ]
        ];
    }

    // Helper method to determine family type
    private function getFamilyType($dataKeluarga)
    {
        if ($dataKeluarga->anak_ke) {
            return 'anak';
        } elseif ($dataKeluarga->nama_pasangan) {
            return 'pasangan';
        } elseif ($dataKeluarga->status_orangtua) {
            return 'orang tua';
        }
        return 'keluarga';
    }

    // Format response
    protected function formatDataKeluarga($dataKeluarga)
    {
        $familyType = $this->getFamilyType($dataKeluarga);
        
        $name = '';
        if ($dataKeluarga->nama) {
            $name = $dataKeluarga->nama;
        } elseif ($dataKeluarga->nama_pasangan) {
            $name = $dataKeluarga->nama_pasangan;
        }

        return [
            'id' => $dataKeluarga->id,
            'family_type' => $familyType,
            'name' => $name,
            'jenis_kelamin' => $dataKeluarga->jenis_kelamin,
            'tempat_lahir' => $dataKeluarga->tempat_lahir,
            'tgl_lahir' => $dataKeluarga->tgl_lahir,
            'umur' => $dataKeluarga->umur,
            'status_pengajuan' => $dataKeluarga->status_pengajuan,
            'keterangan' => $dataKeluarga->keterangan,
            
            // Specific to children
            'anak_ke' => $dataKeluarga->anak_ke,
            'pekerjaan_anak' => $dataKeluarga->pekerjaan_anak,
            
            // Specific to spouse (removed tempat_nikah, tgl_nikah, no_akta_nikah)
            'nama_pasangan' => $dataKeluarga->nama_pasangan,
            'pasangan_berkerja_dalam_satu_instansi' => $dataKeluarga->pasangan_berkerja_dalam_satu_instansi,
            
            // Specific to parents
            'status_orangtua' => $dataKeluarga->status_orangtua,
            'alamat' => $dataKeluarga->alamat,
            'telepon' => $dataKeluarga->telepon,
            'pekerjaan' => $dataKeluarga->pekerjaan,
            
            'timestamps' => [
                'tgl_input' => $dataKeluarga->tgl_input,
                'tgl_diajukan' => $dataKeluarga->tgl_diajukan,
                'tgl_disetujui' => $dataKeluarga->tgl_disetujui,
                'tgl_ditolak' => $dataKeluarga->tgl_ditolak
            ],
            'dokumen' => [
                'file_akte' => $dataKeluarga->file_akte ? [
                    'nama_file' => $dataKeluarga->file_akte,
                    'url' => url('storage/pegawai/keluarga/akte/'.$dataKeluarga->file_akte)
                ] : null,
                'kartu_nikah' => $dataKeluarga->kartu_nikah ? [
                    'nama_file' => $dataKeluarga->kartu_nikah,
                    'url' => url('storage/pegawai/keluarga/nikah/'.$dataKeluarga->kartu_nikah)
                ] : null,
                'file_karpeg_pasangan' => $dataKeluarga->file_karpeg_pasangan ? [
                    'nama_file' => $dataKeluarga->file_karpeg_pasangan,
                    'url' => url('storage/pegawai/keluarga/karpeg/'.$dataKeluarga->file_karpeg_pasangan)
                ] : null
            ],
            'created_at' => $dataKeluarga->created_at,
            'updated_at' => $dataKeluarga->updated_at
        ];
    }
}