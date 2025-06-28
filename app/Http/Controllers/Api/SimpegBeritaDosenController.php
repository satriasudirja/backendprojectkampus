<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegBerita;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegJabatanAkademik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SimpegBeritaDosenController extends Controller
{
    /**
     * Get all berita for logged in dosen
     */
    public function index(Request $request)
    {
        // Pastikan user sudah login
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        // Eager load relasi yang diperlukan untuk dosen
        $dosen = Auth::user()->load([
            'unitKerja', 'statusAktif', 'jabatanAkademik',
            'dataJabatanFungsional' => fn($q) => $q->with('jabatanFungsional')->orderBy('tmt_jabatan', 'desc')->limit(1),
            'dataJabatanStruktural' => fn($q) => $q->with('jabatanStruktural.jenisJabatanStruktural')->orderBy('tgl_mulai', 'desc')->limit(1),
            'dataPendidikanFormal' => fn($q) => $q->with('jenjangPendidikan')->orderBy('jenjang_pendidikan_id', 'desc')->limit(1),
        ]);

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan atau belum login'
            ], 404);
        }

        // --- PERBAIKAN: Ambil semua filter dari request ---
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $judul = $request->input('judul');
        $unitKerjaFilter = $request->input('unit_kerja');
        $jabatanAkademikFilter = $request->input('jabatan_akademik_id'); // Filter baru
        $prioritas = $request->input('prioritas');
        $status = $request->input('status');
        $tglPostingFrom = $request->input('tgl_posting_from');
        $tglPostingTo = $request->input('tgl_posting_to');

        // Query berita dasar yang relevan untuk dosen (berdasarkan unit kerja & jabatan miliknya atau berita umum)
        $query = SimpegBerita::with(['jabatanAkademik'])
            ->where(function ($q) use ($dosen) {
                // Berita untuk unit kerja dosen
                if ($dosen->unit_kerja_id) {
                    $q->whereRaw("unit_kerja_id::jsonb @> ?::jsonb", [json_encode([(string)$dosen->unit_kerja_id])]);
                }

                // Berita untuk jabatan akademik dosen
                if ($dosen->jabatan_akademik_id) {
                    $q->orWhereHas('jabatanAkademik', function ($subQ) use ($dosen) {
                        $subQ->where('jabatan_akademik_id', $dosen->jabatan_akademik_id);
                    });
                }

                // Berita umum (untuk 'semua' unit atau tanpa jabatan spesifik)
                $q->orWhereRaw("unit_kerja_id::jsonb @> '[\"semua\"]'::jsonb");
                $q->orWhereDoesntHave('jabatanAkademik');
            });

        // --- APLIKASIKAN FILTER TAMBAHAN DARI REQUEST ---

        // Filter by search (global)
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', '%' . $search . '%')
                    ->orWhere('konten', 'like', '%' . $search . '%');
            });
        }

        // Filter by judul
        if ($judul) {
            $query->where('judul', 'like', '%' . $judul . '%');
        }
        
        // Filter by unit kerja
        if ($unitKerjaFilter && $unitKerjaFilter != 'semua') {
            $query->whereRaw("unit_kerja_id::jsonb @> ?::jsonb", [json_encode([(string)$unitKerjaFilter])]);
        }

        // PERBAIKAN: Tambahkan filter by Jabatan Akademik dari request
        if ($jabatanAkademikFilter && $jabatanAkademikFilter != 'semua') {
            $query->whereHas('jabatanAkademik', function ($q) use ($jabatanAkademikFilter) {
                $q->where('simpeg_jabatan_akademik.id', $jabatanAkademikFilter);
            });
        }

        // Filter by prioritas
        if ($prioritas !== null && $prioritas !== 'semua') {
            $query->where('prioritas', (bool)$prioritas);
        }

        // Filter tanggal posting
        if ($tglPostingFrom && $tglPostingTo) {
            $query->whereBetween('tgl_posting', [$tglPostingFrom, $tglPostingTo]);
        } elseif ($tglPostingFrom) {
            $query->where('tgl_posting', '>=', $tglPostingFrom);
        } elseif ($tglPostingTo) {
            $query->where('tgl_posting', '<=', $tglPostingTo);
        }

        // Filter by status (active/expired)
        if ($status && $status != 'semua') {
            if ($status === 'active') {
                $query->where(function ($q) {
                    $q->whereNull('tgl_expired')
                        ->orWhere('tgl_expired', '>=', now()->toDateString());
                });
            } elseif ($status === 'expired') {
                $query->where('tgl_expired', '<', now()->toDateString());
            }
        }

        // Execute query dengan pagination
        $beritaList = $query->orderBy('prioritas', 'desc')
            ->orderBy('tgl_posting', 'desc')
            ->paginate($perPage);

        // Transform the collection to include formatted data with action URLs
        $beritaList->getCollection()->transform(function ($item) {
            return $this->formatBerita($item, true);
        });

        return response()->json([
            'success' => true,
            'data' => $beritaList,
            'empty_data' => $beritaList->isEmpty(),
            'dosen_info' => $this->formatDosenInfo($dosen),
            'filters' => [
                'unit_kerja' => $this->getUnitKerjaOptions(),
                'prioritas' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => '1', 'nama' => 'Prioritas'],
                    ['id' => '0', 'nama' => 'Normal']
                ],
                'status' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'active', 'nama' => 'Aktif'],
                    ['id' => 'expired', 'nama' => 'Expired']
                ]
            ],
            'table_columns' => [
                ['field' => 'unit_kerja', 'label' => 'Unit Kerja', 'sortable' => true, 'sortable_field' => 'unit_kerja_id'],
                ['field' => 'judul', 'label' => 'Judul', 'sortable' => true, 'sortable_field' => 'judul'],
                ['field' => 'tgl_posting', 'label' => 'Tgl. Posting', 'sortable' => true, 'sortable_field' => 'tgl_posting'],
                ['field' => 'tgl_expired', 'label' => 'Tgl. Expired', 'sortable' => true, 'sortable_field' => 'tgl_expired'],
                ['field' => 'prioritas', 'label' => 'Prioritas', 'sortable' => true, 'sortable_field' => 'prioritas'],
                ['field' => 'aksi', 'label' => 'Aksi', 'sortable' => false]
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'pagination' => [
                'current_page' => $beritaList->currentPage(),
                'per_page' => $beritaList->perPage(),
                'total' => $beritaList->total(),
                'last_page' => $beritaList->lastPage(),
                'from' => $beritaList->firstItem(),
                'to' => $beritaList->lastItem()
            ]
        ]);
    }

    /**
     * Get detail berita
     */
    public function show($id)
    {
        $dosen = Auth::user();

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $berita = SimpegBerita::with(['jabatanAkademik'])->find($id);

        if (!$berita) {
            return response()->json([
                'success' => false,
                'message' => 'Berita tidak ditemukan'
            ], 404);
        }

        // Check access permission
        $hasAccess = $this->checkBeritaAccess($berita, $dosen);

        if (!$hasAccess) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat berita ini'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'dosen' => $this->formatDosenInfo($dosen->load([
                'unitKerja', 'statusAktif', 'jabatanAkademik',
                'dataJabatanFungsional.jabatanFungsional',
                'dataJabatanStruktural.jabatanStruktural.jenisJabatanStruktural',
                'dataPendidikanFormal.jenjangPendidikan'
            ])),
            'data' => $this->formatBerita($berita)
        ]);
    }

    /**
     * Get status statistics untuk dashboard
     */
    public function getStatusStatistics()
    {
        $dosen = Auth::user();

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        // Get berita statistics for dosen
        $query = SimpegBerita::where(function ($q) use ($dosen) {
            if ($dosen->unit_kerja_id) {
                $q->whereRaw("unit_kerja_id::jsonb @> ?::jsonb", [json_encode([(string)$dosen->unit_kerja_id])]);
            }
            if ($dosen->jabatan_akademik_id) {
                $q->orWhereHas('jabatanAkademik', fn($subQ) => $subQ->where('jabatan_akademik_id', $dosen->jabatan_akademik_id));
            }
            $q->orWhereRaw("unit_kerja_id::jsonb @> '[\"semua\"]'::jsonb");
            $q->orWhereDoesntHave('jabatanAkademik');
        });

        $total = $query->count();
        $prioritas = (clone $query)->where('prioritas', true)->count();
        $active = (clone $query)->where(function ($q) {
            $q->whereNull('tgl_expired')
                ->orWhere('tgl_expired', '>=', now()->toDateString());
        })->count();
        $expired = (clone $query)->where('tgl_expired', '<', now()->toDateString())->count();


        $statistics = [
            'total' => $total,
            'prioritas' => $prioritas,
            'normal' => $total - $prioritas,
            'active' => $active,
            'expired' => $expired
        ];

        return response()->json([
            'success' => true,
            'statistics' => $statistics
        ]);
    }

    /**
     * Get filter options
     */
    public function getFilterOptions()
    {
        if (!Auth::check()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'filter_options' => [
                'unit_kerja' => $this->getUnitKerjaOptions(),
                'prioritas' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => '1', 'nama' => 'Prioritas'],
                    ['id' => '0', 'nama' => 'Normal']
                ],
                'status' => [
                    ['id' => 'semua', 'nama' => 'Semua'],
                    ['id' => 'active', 'nama' => 'Aktif'],
                    ['id' => 'expired', 'nama' => 'Expired']
                ]
            ]
        ]);
    }

    /**
     * Download berita file
     */
    public function downloadFile($id)
    {
        $dosen = Auth::user();

        if (!$dosen) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $berita = SimpegBerita::find($id);

        if (!$berita) {
            return response()->json(['success' => false, 'message' => 'Berita tidak ditemukan'], 404);
        }

        // Check access permission
        if (!$this->checkBeritaAccess($berita, $dosen)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki akses untuk mengunduh file ini'], 403);
        }

        if (!$berita->file_berita) {
            return response()->json(['success' => false, 'message' => 'File tidak tersedia'], 404);
        }

        $filePath = 'public/berita/files/' . $berita->file_berita;

        if (!Storage::exists($filePath)) {
            return response()->json(['success' => false, 'message' => 'File tidak ditemukan di server'], 404);
        }

        return Storage::download($filePath, $berita->file_berita);
    }
    
    // --- Helper Methods ---

    /**
     * Helper: Check berita access for dosen
     */
    public function checkBeritaAccess($berita, $dosen)
    {
        // Decode unit_kerja_id jika masih dalam bentuk string JSON
        $targetUnitIds = is_array($berita->unit_kerja_id) ? $berita->unit_kerja_id : json_decode($berita->unit_kerja_id, true);

        // Jika berita untuk semua unit, beri akses
        if (is_array($targetUnitIds) && in_array('semua', $targetUnitIds)) {
            return true;
        }

        // Cek apakah unit kerja dosen ada di dalam target berita
        if (is_array($targetUnitIds) && in_array((string)$dosen->unit_kerja_id, $targetUnitIds)) {
            return true;
        }
        
        // Cek apakah berita untuk jabatan akademik dosen
        if ($dosen->jabatan_akademik_id) {
            if ($berita->jabatanAkademik()->where('jabatan_akademik_id', $dosen->jabatan_akademik_id)->exists()) {
                return true;
            }
        }

        // Beri akses jika berita ini umum (tidak ada target jabatan) dan dosen tidak memiliki unit kerja spesifik yang cocok di atas
        if ($berita->jabatanAkademik->isEmpty()) {
            return true;
        }

        return false;
    }

    /**
     * Helper: Get unit kerja options
     */
    public function getUnitKerjaOptions()
    {
        $unitKerjaList = SimpegUnitKerja::select('id', 'nama_unit')
            ->orderBy('nama_unit')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => (string)$item->id,
                    'nama' => $item->nama_unit
                ];
            });

        return array_merge(
            [['id' => 'semua', 'nama' => 'Semua Unit Kerja']],
            $unitKerjaList->toArray()
        );
    }

    /**
     * Helper: Format dosen info
     */
    private function formatDosenInfo($dosen)
    {
        $jabatanAkademikNama = $dosen->jabatanAkademik->jabatan_akademik ?? '-';
        $jabatanFungsionalNama = $dosen->dataJabatanFungsional->first()->jabatanFungsional->nama_jabatan_fungsional ?? '-';
        $unitKerjaNama = $dosen->unitKerja->nama_unit ?? 'Tidak Ada';

        return [
            'id' => $dosen->id,
            'nip' => $dosen->nip ?? '-',
            'nama' => $dosen->nama ?? '-',
            'unit_kerja' => $unitKerjaNama,
            'status' => $dosen->statusAktif->nama_status_aktif ?? '-',
            'jab_akademik' => $jabatanAkademikNama,
            'jab_fungsional' => $jabatanFungsionalNama
        ];
    }

    /**
     * Helper: Format berita response
     */
    protected function formatBerita($berita, $includeActions = true)
    {
        $isExpired = $berita->tgl_expired && $berita->tgl_expired < now()->toDateString();
        $status = $isExpired ? 'expired' : 'active';
        $statusInfo = $this->getStatusInfo($status);

        // Get unit kerja names
        $unitKerjaNama = [];
        $unitKerjaIds = is_array($berita->unit_kerja_id) ? $berita->unit_kerja_id : json_decode($berita->unit_kerja_id, true);

        if (is_array($unitKerjaIds)) {
            foreach ($unitKerjaIds as $unitId) {
                if ($unitId === 'semua') {
                    $unitKerjaNama[] = 'Semua Unit Kerja';
                } else {
                    // PERBAIKAN UTAMA: Atasi error "invalid text representation for type bigint"
                    // dengan memastikan $unitId adalah integer sebelum query.
                    $unitKerja = SimpegUnitKerja::find((int)$unitId);
                    $unitKerjaNama[] = $unitKerja ? $unitKerja->nama_unit : "Unit Tidak Dikenal";
                }
            }
        }

        $jabatanAkademikNama = $berita->jabatanAkademik->pluck('jabatan_akademik')->toArray();

        $data = [
            'id' => $berita->id,
            'judul' => $berita->judul,
            'konten' => $berita->konten,
            'slug' => $berita->slug,
            'tgl_posting' => $berita->tgl_posting ? \Carbon\Carbon::parse($berita->tgl_posting)->isoFormat('D MMMM YYYY') : null,
            'tgl_expired' => $berita->tgl_expired ? \Carbon\Carbon::parse($berita->tgl_expired)->isoFormat('D MMMM YYYY') : null,
            'prioritas' => $berita->prioritas,
            'prioritas_label' => $berita->prioritas ? 'Prioritas' : 'Normal',
            'unit_kerja' => implode(', ', $unitKerjaNama),
            'unit_kerja_array' => $unitKerjaNama,
            'jabatan_akademik' => !empty($jabatanAkademikNama) ? implode(', ', $jabatanAkademikNama) : 'Semua Jabatan',
            'jabatan_akademik_array' => $jabatanAkademikNama,
            'status' => $status,
            'status_info' => $statusInfo,
            'has_gambar' => !empty($berita->gambar_berita),
            'has_file' => !empty($berita->file_berita),
            'gambar_berita_url' => $berita->gambar_berita ? url('storage/berita/images/' . $berita->gambar_berita) : null,
            'file_berita_url' => $berita->file_berita ? url('storage/berita/files/' . $berita->file_berita) : null,
            'created_at' => $berita->created_at,
            'updated_at' => $berita->updated_at
        ];

        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/dosen/berita/{$berita->id}"),
                'download_url' => $berita->file_berita ? url("/api/dosen/berita/{$berita->id}/download") : null,
                'share_url' => url("/berita/{$berita->slug}")
            ];
        }

        return $data;
    }

    /**
     * Helper: Get status info
     */
    public function getStatusInfo($status)
    {
        $statusMap = [
            'active' => ['label' => 'Aktif', 'color' => 'success', 'icon' => 'check-circle'],
            'expired' => ['label' => 'Expired', 'color' => 'danger', 'icon' => 'x-circle']
        ];
        return $statusMap[$status] ?? ['label' => ucfirst($status), 'color' => 'secondary', 'icon' => 'circle'];
    }
}
