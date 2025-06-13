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
    // Get all berita for logged in dosen
    public function index(Request $request) 
    {
        // Pastikan user sudah login
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized - Silakan login terlebih dahulu'
            ], 401);
        }

        // Eager load semua relasi yang diperlukan untuk menghindari N+1 query problem
        $dosen = Auth::user()->load([
            'unitKerja',
            'statusAktif', 
            'jabatanAkademik',
            'dataJabatanFungsional' => function($query) {
                $query->with('jabatanFungsional')
                      ->orderBy('tmt_jabatan', 'desc')
                      ->limit(1);
            },
            'dataJabatanStruktural' => function($query) {
                $query->with('jabatanStruktural.jenisJabatanStruktural')
                      ->orderBy('tgl_mulai', 'desc')
                      ->limit(1);
            },
            'dataPendidikanFormal' => function($query) {
                $query->with('jenjangPendidikan')
                      ->orderBy('jenjang_pendidikan_id', 'desc')
                      ->limit(1);
            }
        ]);

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan atau belum login'
            ], 404);
        }

        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $unitKerja = $request->unit_kerja;
        $prioritas = $request->prioritas;
        $status = $request->status;

        // Query berita yang relevan untuk dosen
        $query = SimpegBerita::with(['jabatanAkademik'])
            ->where(function($q) use ($dosen) {
                // Berita untuk unit kerja dosen (menggunakan ID integer)
                if ($dosen->unit_kerja_id) {
                    $q->whereRaw("unit_kerja_id::jsonb @> ?::jsonb", [json_encode([(int)$dosen->unit_kerja_id])]);
                }
                
                // Berita untuk jabatan akademik dosen
                if ($dosen->jabatan_akademik_id) {
                    $q->orWhereHas('jabatanAkademik', function($subQ) use ($dosen) {
                        $subQ->where('jabatan_akademik_id', $dosen->jabatan_akademik_id);
                    });
                }
                
                // Berita yang tidak memiliki jabatan akademik spesifik (berita umum)
                $q->orWhereDoesntHave('jabatanAkademik');
            });

        // Filter by search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('judul', 'like', '%'.$search.'%')
                  ->orWhere('konten', 'like', '%'.$search.'%')
                  ->orWhere('slug', 'like', '%'.$search.'%');
            });
        }

        // Filter by unit kerja
        if ($unitKerja && $unitKerja != 'semua') {
            // Konversi ke integer jika berupa angka
            $unitKerjaFilter = is_numeric($unitKerja) ? (int)$unitKerja : $unitKerja;
            $query->whereRaw("unit_kerja_id::jsonb @> ?::jsonb", [json_encode([$unitKerjaFilter])]);
        }

        // Filter by prioritas
        if ($prioritas !== null && $prioritas !== 'semua') {
            $query->where('prioritas', (bool)$prioritas);
        }

        // Filter by status (active/expired)
        if ($status && $status != 'semua') {
            if ($status === 'active') {
                $query->where(function($q) {
                    $q->whereNull('tgl_expired')
                      ->orWhere('tgl_expired', '>=', now()->toDateString());
                });
            } elseif ($status === 'expired') {
                $query->where('tgl_expired', '<', now()->toDateString());
            }
        }

        // Additional filters
        if ($request->filled('judul')) {
            $query->where('judul', 'like', '%'.$request->judul.'%');
        }
        if ($request->filled('tgl_posting')) {
            $query->whereDate('tgl_posting', $request->tgl_posting);
        }
        if ($request->filled('tgl_expired')) {
            $query->whereDate('tgl_expired', $request->tgl_expired);
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

    // Get detail berita
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

    // Get status statistics untuk dashboard
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
        $query = SimpegBerita::where(function($q) use ($dosen) {
            // Berita untuk unit kerja dosen (menggunakan ID integer)
            if ($dosen->unit_kerja_id) {
                $q->whereRaw("unit_kerja_id::jsonb @> ?::jsonb", [json_encode([(int)$dosen->unit_kerja_id])]);
            }
            
            // Berita untuk jabatan akademik dosen
            if ($dosen->jabatan_akademik_id) {
                $q->orWhereHas('jabatanAkademik', function($subQ) use ($dosen) {
                    $subQ->where('jabatan_akademik_id', $dosen->jabatan_akademik_id);
                });
            }
            
            // Berita yang tidak memiliki jabatan akademik spesifik (berita umum)
            $q->orWhereDoesntHave('jabatanAkademik');
        });

        $total = $query->count();
        $prioritas = $query->where('prioritas', true)->count();
        $active = $query->where(function($q) {
            $q->whereNull('tgl_expired')
              ->orWhere('tgl_expired', '>=', now()->toDateString());
        })->count();
        $expired = $query->where('tgl_expired', '<', now()->toDateString())->count();

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

    // Get filter options
    public function getFilterOptions()
    {
        $dosen = Auth::user();

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
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

    // Get available actions
    public function getAvailableActions()
    {
        return response()->json([
            'success' => true,
            'actions' => [
                'single' => [
                    [
                        'key' => 'view',
                        'label' => 'Lihat Detail',
                        'icon' => 'eye',
                        'color' => 'info'
                    ],
                    [
                        'key' => 'download_file',
                        'label' => 'Download File',
                        'icon' => 'download',
                        'color' => 'success',
                        'condition' => 'has_file'
                    ],
                    [
                        'key' => 'share',
                        'label' => 'Bagikan',
                        'icon' => 'share',
                        'color' => 'primary'
                    ]
                ]
            ]
        ]);
    }

    // Get system configuration
    public function getSystemConfig()
    {
        $config = [
            'allow_download' => env('ALLOW_BERITA_DOWNLOAD', true),
            'show_expired' => env('SHOW_EXPIRED_BERITA', true),
            'max_file_size' => env('MAX_BERITA_FILE_SIZE', 5120), // KB
            'allowed_file_types' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']
        ];

        return response()->json([
            'success' => true,
            'config' => $config
        ]);
    }

    // Download berita file
    public function downloadFile($id)
    {
        $dosen = Auth::user();

        if (!$dosen) {
            return response()->json([
                'success' => false,
                'message' => 'Data dosen tidak ditemukan'
            ], 404);
        }

        $berita = SimpegBerita::find($id);

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
                'message' => 'Anda tidak memiliki akses untuk mengunduh file ini'
            ], 403);
        }

        if (!$berita->file_berita) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak tersedia'
            ], 404);
        }

        $filePath = 'public/berita/files/' . $berita->file_berita;
        
        if (!Storage::exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan di server'
            ], 404);
        }

        // Log download activity
        ActivityLogger::log('download', $berita, [
            'file_name' => $berita->file_berita,
            'downloaded_by' => $dosen->id
        ]);

        return Storage::download($filePath, $berita->file_berita);
    }

    // Helper: Check berita access for dosen
    private function checkBeritaAccess($berita, $dosen)
    {
        // Check if berita is for dosen's unit kerja
        if (is_array($berita->unit_kerja_id)) {
            // Convert dosen unit_kerja_id to integer for comparison
            $dosenUnitId = (int)$dosen->unit_kerja_id;
            
            foreach ($berita->unit_kerja_id as $unitId) {
                if ($unitId === 'semua' || (int)$unitId === $dosenUnitId) {
                    return true;
                }
            }
        } else {
            // Handle single unit_kerja_id
            if ((int)$berita->unit_kerja_id === (int)$dosen->unit_kerja_id) {
                return true;
            }
        }

        // Check if berita is for dosen's jabatan akademik
        if ($dosen->jabatan_akademik_id) {
            $hasJabatanAccess = $berita->jabatanAkademik()
                ->where('jabatan_akademik_id', $dosen->jabatan_akademik_id)
                ->exists();
            
            if ($hasJabatanAccess) {
                return true;
            }
        }

        // Allow access if no specific jabatan akademik is set (berita umum)
        if ($berita->jabatanAkademik->isEmpty()) {
            return true;
        }

        return false;
    }

    // Helper: Get unit kerja options
    private function getUnitKerjaOptions()
    {
        $unitKerjaList = SimpegUnitKerja::select('id', 'nama_unit')
            ->orderBy('nama_unit')
            ->get()
            ->map(function($item) {
                return [
                    'id' => (string)$item->id, // Convert to string for consistency
                    'nama' => $item->nama_unit
                ];
            });

        return array_merge(
            [['id' => 'semua', 'nama' => 'Semua Unit Kerja']],
            $unitKerjaList->toArray()
        );
    }

    // Helper: Format dosen info
    private function formatDosenInfo($dosen)
    {
        $jabatanAkademikNama = '-';
        if ($dosen->jabatanAkademik) {
            $jabatanAkademikNama = $dosen->jabatanAkademik->jabatan_akademik ?? '-';
        }

        $jabatanFungsionalNama = '-';
        if ($dosen->dataJabatanFungsional && $dosen->dataJabatanFungsional->isNotEmpty()) {
            $jabatanFungsional = $dosen->dataJabatanFungsional->first()->jabatanFungsional;
            if ($jabatanFungsional) {
                if (isset($jabatanFungsional->nama_jabatan_fungsional)) {
                    $jabatanFungsionalNama = $jabatanFungsional->nama_jabatan_fungsional;
                } elseif (isset($jabatanFungsional->nama)) {
                    $jabatanFungsionalNama = $jabatanFungsional->nama;
                }
            }
        }

        $unitKerjaNama = 'Tidak Ada';
        if ($dosen->unitKerja) {
            $unitKerjaNama = $dosen->unitKerja->nama_unit;
        } elseif ($dosen->unit_kerja_id) {
            $unitKerja = SimpegUnitKerja::find($dosen->unit_kerja_id);
            $unitKerjaNama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $dosen->unit_kerja_id;
        }

        return [
            'id' => $dosen->id,
            'nip' => $dosen->nip ?? '-',
            'nama' => $dosen->nama ?? '-',
            'unit_kerja' => $unitKerjaNama,
            'status' => $dosen->statusAktif ? $dosen->statusAktif->nama_status_aktif : '-',
            'jab_akademik' => $jabatanAkademikNama,
            'jab_fungsional' => $jabatanFungsionalNama
        ];
    }

    // Helper: Format berita response
    protected function formatBerita($berita, $includeActions = true)
    {
        // Determine status
        $isExpired = $berita->tgl_expired && $berita->tgl_expired < now()->toDateString();
        $status = $isExpired ? 'expired' : 'active';
        $statusInfo = $this->getStatusInfo($status);
        
        // Get unit kerja names
        $unitKerjaNama = [];
        if (is_array($berita->unit_kerja_id)) {
            foreach ($berita->unit_kerja_id as $unitId) {
                if ($unitId === 'semua') {
                    $unitKerjaNama[] = 'Semua Unit';
                } else {
                    // Handle both string and integer IDs
                    $unitKerja = SimpegUnitKerja::where('id', $unitId)->first();
                    if (!$unitKerja) {
                        // Fallback to kode_unit if not found by ID
                        $unitKerja = SimpegUnitKerja::where('kode_unit', $unitId)->first();
                    }
                    $unitKerjaNama[] = $unitKerja ? $unitKerja->nama_unit : "Unit #{$unitId}";
                }
            }
        } else {
            // Handle single ID (backward compatibility)
            $unitKerja = SimpegUnitKerja::where('id', $berita->unit_kerja_id)->first();
            if (!$unitKerja) {
                $unitKerja = SimpegUnitKerja::where('kode_unit', $berita->unit_kerja_id)->first();
            }
            $unitKerjaNama[] = $unitKerja ? $unitKerja->nama_unit : "Unit #{$berita->unit_kerja_id}";
        }
        
        // Get jabatan akademik names
        $jabatanAkademikNama = $berita->jabatanAkademik->pluck('jabatan_akademik')->toArray();
        
        $data = [
            'id' => $berita->id,
            'judul' => $berita->judul,
            'konten' => $berita->konten,
            'slug' => $berita->slug,
            'tgl_posting' => $berita->tgl_posting,
            'tgl_expired' => $berita->tgl_expired,
            'prioritas' => $berita->prioritas,
            'prioritas_label' => $berita->prioritas ? 'Prioritas' : 'Normal',
            'unit_kerja' => implode(', ', $unitKerjaNama),
            'unit_kerja_array' => $unitKerjaNama,
            'jabatan_akademik' => implode(', ', $jabatanAkademikNama),
            'jabatan_akademik_array' => $jabatanAkademikNama,
            'status' => $status,
            'status_info' => $statusInfo,
            'has_gambar' => !empty($berita->gambar_berita),
            'has_file' => !empty($berita->file_berita),
            'gambar_berita' => $berita->gambar_berita ? [
                'nama_file' => $berita->gambar_berita,
                'url' => url('storage/berita/images/'.$berita->gambar_berita)
            ] : null,
            'file_berita' => $berita->file_berita ? [
                'nama_file' => $berita->file_berita,
                'url' => url('storage/berita/files/'.$berita->file_berita),
                'download_url' => url("/api/dosen/berita/{$berita->id}/download")
            ] : null,
            'created_at' => $berita->created_at,
            'updated_at' => $berita->updated_at
        ];

        // Add action URLs if requested
        if ($includeActions) {
            $data['aksi'] = [
                'detail_url' => url("/api/dosen/berita/{$berita->id}"),
                'download_url' => $berita->file_berita ? url("/api/dosen/berita/{$berita->id}/download") : null,
            ];

            $data['actions'] = [];
            
            // Always allow view
            $data['actions']['view'] = [
                'url' => $data['aksi']['detail_url'],
                'method' => 'GET',
                'label' => 'Lihat Detail',
                'icon' => 'eye',
                'color' => 'info'
            ];
            
            // Allow download if file exists
            if ($berita->file_berita) {
                $data['actions']['download'] = [
                    'url' => $data['aksi']['download_url'],
                    'method' => 'GET',
                    'label' => 'Download File',
                    'icon' => 'download',
                    'color' => 'success'
                ];
            }
            
            // Share action
            $data['actions']['share'] = [
                'url' => url("/berita/{$berita->slug}"),
                'method' => 'GET',
                'label' => 'Bagikan',
                'icon' => 'share',
                'color' => 'primary'
            ];
        }

        return $data;
    }

    // Helper: Get status info
    private function getStatusInfo($status)
    {
        $statusMap = [
            'active' => [
                'label' => 'Aktif',
                'color' => 'success',
                'icon' => 'check-circle',
                'description' => 'Berita masih berlaku'
            ],
            'expired' => [
                'label' => 'Expired',
                'color' => 'danger',
                'icon' => 'x-circle',
                'description' => 'Berita sudah expired'
            ]
        ];

        return $statusMap[$status] ?? [
            'label' => ucfirst($status),
            'color' => 'secondary',
            'icon' => 'circle',
            'description' => ''
        ];
    }
}