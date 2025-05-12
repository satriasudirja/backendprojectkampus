<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\SimpegPegawai;
use App\Models\SimpegUnitKerja;
use App\Models\SimpegStatusAktif;
use App\Models\SimpegJabatanAkademik;
use App\Models\SimpegDataHubunganKerja;
use App\Models\SimpegDataPendidikanFormal;
use App\Models\SimpegJenjangPendidikan;
use App\Models\SimpegBerita;

class AdminDashboardService
{
    /**
     * Get all child unit IDs recursively for a given unit
     */
  /**
 * Get all child unit IDs recursively for a given unit
 */
protected function getAllChildUnitIds($unitKerjaId = null)
{
    if (!$unitKerjaId) {
        // Jika tidak ada unit yang ditentukan, kembalikan semua unit yang valid untuk kolom integer
        return SimpegUnitKerja::pluck('kode_unit')
            ->filter(function($value) {
                // Filter hanya nilai yang bisa dikonversi ke integer
                return is_numeric($value) && !str_contains($value, '.') && !preg_match('/[a-zA-Z]/', $value);
            })
            ->toArray();
    }

    // Ambil unit
    $unit = SimpegUnitKerja::where('kode_unit', $unitKerjaId)->first();
    if (!$unit) {
        return [];
    }

    // Ambil semua unit anak langsung yang valid untuk kolom integer
    $childUnits = SimpegUnitKerja::where('parent_unit_id', $unit->kode_unit)
        ->pluck('kode_unit')
        ->filter(function($value) {
            // Filter hanya nilai yang bisa dikonversi ke integer
            return is_numeric($value) && !str_contains($value, '.') && !preg_match('/[a-zA-Z]/', $value);
        })
        ->toArray();

    // Tambahkan semua unit cucunya secara rekursif
    $allChildIds = $childUnits;
    foreach ($childUnits as $childId) {
        $allChildIds = array_merge($allChildIds, $this->getAllChildUnitIds($childId));
    }

    // Tambahkan ID unit saat ini juga jika valid untuk kolom integer
    if (is_numeric($unit->kode_unit) && !str_contains($unit->kode_unit, '.') && !preg_match('/[a-zA-Z]/', $unit->kode_unit)) {
        $allChildIds[] = $unit->kode_unit;
    }

    return array_unique($allChildIds);
}

    /**
     * Get staff summary data
     */
    public function getStaffSummary($unitKerjaId = null)
    {
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);

        // Query dasar untuk pegawai di unit yang dipilih
        $query = SimpegPegawai::whereIn('unit_kerja_id', $unitIds);

        // Pegawai aktif (status_keluar = false)
        $activeEmployees = (clone $query)
            ->join('simpeg_status_aktif', 'simpeg_pegawai.status_aktif_id', '=', 'simpeg_status_aktif.id')
            ->where('simpeg_status_aktif.status_keluar', false)
            ->count();

        // Pegawai tidak aktif (status_keluar = true)
        $inactiveEmployees = (clone $query)
            ->join('simpeg_status_aktif', 'simpeg_pegawai.status_aktif_id', '=', 'simpeg_status_aktif.id')
            ->where('simpeg_status_aktif.status_keluar', true)
            ->count();

        // Staf akademik (role = Dosen, Dosen Praktisi/Industri)
        $academicStaff = (clone $query)
            ->join('simpeg_jabatan_akademik', 'simpeg_pegawai.jabatan_akademik_id', '=', 'simpeg_jabatan_akademik.id')
            ->join('simpeg_users_roles', 'simpeg_jabatan_akademik.role_id', '=', 'simpeg_users_roles.id')
            ->whereIn('simpeg_users_roles.nama', ['Dosen', 'Dosen Praktisi/Industri'])
            ->count();

        // Staf non-akademik (role = Tenaga Kependidikan)
        $nonAcademicStaff = (clone $query)
            ->join('simpeg_jabatan_akademik', 'simpeg_pegawai.jabatan_akademik_id', '=', 'simpeg_jabatan_akademik.id')
            ->join('simpeg_users_roles', 'simpeg_jabatan_akademik.role_id', '=', 'simpeg_users_roles.id')
            ->where('simpeg_users_roles.nama', 'Tenaga Kependidikan')
            ->count();

        return [
            'active_employees' => $activeEmployees,
            'inactive_employees' => $inactiveEmployees,
            'academic_staff' => $academicStaff,
            'non_academic_staff' => $nonAcademicStaff,
        ];
    }

    /**
     * Get academic vs non-academic staff distribution
     */
    public function getStaffDistribution($unitKerjaId = null)
    {
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);
        $baseQuery = SimpegPegawai::whereIn('unit_kerja_id', $unitIds);

        // Get academic staff count
        $academicStaff = (clone $baseQuery)
            ->join('simpeg_jabatan_akademik', 'simpeg_pegawai.jabatan_akademik_id', '=', 'simpeg_jabatan_akademik.id')
            ->join('simpeg_users_roles', 'simpeg_jabatan_akademik.role_id', '=', 'simpeg_users_roles.id')
            ->whereIn('simpeg_users_roles.nama', ['Dosen', 'Dosen Praktisi/Industri'])
            ->count();

        // Get non-academic staff count
        $nonAcademicStaff = (clone $baseQuery)
            ->join('simpeg_jabatan_akademik', 'simpeg_pegawai.jabatan_akademik_id', '=', 'simpeg_jabatan_akademik.id')
            ->join('simpeg_users_roles', 'simpeg_jabatan_akademik.role_id', '=', 'simpeg_users_roles.id')
            ->where('simpeg_users_roles.nama', 'Tenaga Kependidikan')
            ->count();

        $totalStaff = $academicStaff + $nonAcademicStaff;
        
        // Calculate percentages for donut chart
        $academicPercentage = $totalStaff > 0 ? round(($academicStaff / $totalStaff) * 100, 2) : 0;
        $nonAcademicPercentage = $totalStaff > 0 ? round(($nonAcademicStaff / $totalStaff) * 100, 2) : 0;

        // Prepare chart data (without backgroundColor and hoverBackgroundColor)
        $chartData = [
            'labels' => ['Akademik', 'Non Akademik'],
            'datasets' => [
                [
                    'data' => [$academicStaff, $nonAcademicStaff]
                ],
            ],
        ];

        // Prepare table data
        $tableData = [
            'headers' => ['No', 'Fungsional', 'Jumlah'],
            'rows' => [
                [1, 'Akademik', $academicStaff],
                [2, 'Non Akademik', $nonAcademicStaff],
            ],
            'total' => $totalStaff,
        ];

        return [
            'chart_data' => $chartData,
            'table_data' => $tableData,
            'percentages' => [
                'academic' => $academicPercentage,
                'non_academic' => $nonAcademicPercentage
            ]
        ];
    }

    /**
     * Get work relationship distribution
     */
    public function getWorkRelationships($unitKerjaId = null)
    {
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);
        
        // Get employee IDs for the selected units
        $employeeIds = SimpegPegawai::whereIn('unit_kerja_id', $unitIds)->pluck('id')->toArray();

        // Jika tidak ada pegawai yang ditemukan, kembalikan data kosong
        if (empty($employeeIds)) {
            return [
                'chart_data' => [
                    'labels' => [],
                    'datasets' => [
                        [
                            'label' => 'Jumlah Pegawai',
                            'data' => []
                        ],
                    ],
                ],
                'table_data' => [
                    'headers' => ['Kode', 'Hubungan Kerja', 'Jumlah'],
                    'rows' => [],
                    'total' => 0,
                ],
            ];
        }

        // Sesuaikan dengan nama kolom yang benar pada tabel simpeg_hubungan_kerja
        // Catatan: Gunakan nama_hub_kerja jika itu adalah nama kolom yang benar
        $workRelationships = DB::table('simpeg_data_hubungan_kerja')
            ->join('simpeg_hubungan_kerja', 'simpeg_data_hubungan_kerja.hubungan_kerja_id', '=', 'simpeg_hubungan_kerja.id')
            ->whereIn('simpeg_data_hubungan_kerja.pegawai_id', $employeeIds)
            ->select(
                'simpeg_hubungan_kerja.kode',
                'simpeg_hubungan_kerja.nama_hub_kerja as hubungan_kerja', // Ubah sesuai nama kolom yang benar
                DB::raw('COUNT(*) as jumlah')
            )
            ->groupBy(
                'simpeg_hubungan_kerja.kode', 
                'simpeg_hubungan_kerja.nama_hub_kerja' // Ubah sesuai nama kolom yang benar
            )
            ->orderBy('simpeg_hubungan_kerja.kode')
            ->get();

        // Calculate total
        $total = $workRelationships->sum('jumlah');

        // Prepare chart data
        $chartData = [
            'labels' => $workRelationships->pluck('kode')->toArray(),
            'datasets' => [
                [
                    'label' => 'Jumlah Pegawai',
                    'data' => $workRelationships->pluck('jumlah')->toArray()
                ],
            ],
        ];

        // Prepare table data
        $tableRows = [];
        foreach ($workRelationships as $relationship) {
            $tableRows[] = [
                'kode' => $relationship->kode,
                'hubungan_kerja' => $relationship->hubungan_kerja,
                'jumlah' => $relationship->jumlah
            ];
        }

        $tableData = [
            'headers' => ['Kode', 'Hubungan Kerja', 'Jumlah'],
            'rows' => $tableRows,
            'total' => $total,
        ];

        return [
            'chart_data' => $chartData,
            'table_data' => $tableData,
        ];
    }

    /**
     * Get academic education distribution
     */
    public function getAcademicEducationDistribution($unitKerjaId = null)
    {
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);
        
        // Ambil IDs pegawai akademik
        $academicEmployeeIds = SimpegPegawai::whereIn('unit_kerja_id', $unitIds)
            ->join('simpeg_jabatan_akademik', 'simpeg_pegawai.jabatan_akademik_id', '=', 'simpeg_jabatan_akademik.id')
            ->join('simpeg_users_roles', 'simpeg_jabatan_akademik.role_id', '=', 'simpeg_users_roles.id')
            ->whereIn('simpeg_users_roles.nama', ['Dosen', 'Dosen Praktisi/Industri'])
            ->pluck('simpeg_pegawai.id')
            ->toArray();

        // Jika tidak ada pegawai akademik, kembalikan data kosong
        if (empty($academicEmployeeIds)) {
            return $this->getEmptyEducationDistribution();
        }

        // Dapatkan data distribusi pendidikan
        return $this->getEducationDistributionByEmployeeIds($academicEmployeeIds, 'Akademik');
    }

    /**
     * Get non-academic education distribution
     */
    public function getNonAcademicEducationDistribution($unitKerjaId = null)
    {
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);
        
        // Ambil IDs pegawai non-akademik
        $nonAcademicEmployeeIds = SimpegPegawai::whereIn('unit_kerja_id', $unitIds)
            ->join('simpeg_jabatan_akademik', 'simpeg_pegawai.jabatan_akademik_id', '=', 'simpeg_jabatan_akademik.id')
            ->join('simpeg_users_roles', 'simpeg_jabatan_akademik.role_id', '=', 'simpeg_users_roles.id')
            ->where('simpeg_users_roles.nama', 'Tenaga Kependidikan')
            ->pluck('simpeg_pegawai.id')
            ->toArray();

        // Jika tidak ada pegawai non-akademik, kembalikan data kosong
        if (empty($nonAcademicEmployeeIds)) {
            return $this->getEmptyEducationDistribution();
        }

        // Dapatkan data distribusi pendidikan
        return $this->getEducationDistributionByEmployeeIds($nonAcademicEmployeeIds, 'Non-Akademik');
    }

    /**
     * Get empty education distribution structure
     */
    private function getEmptyEducationDistribution()
    {
        return [
            'chart_data' => [
                'labels' => [],
                'datasets' => [
                    [
                        'label' => 'Jumlah Pegawai',
                        'data' => []
                    ],
                ],
            ],
            'table_data' => [
                'headers' => ['Kode', 'Jenjang Pendidikan', 'Jumlah'],
                'rows' => [],
                'total' => 0,
            ],
        ];
    }

    /**
     * Get education distribution by employee IDs
     */
    private function getEducationDistributionByEmployeeIds($employeeIds, $staffType = '')
    {
        // Get education level distribution
        $educationLevels = DB::table('simpeg_data_pendidikan_formal')
            ->join('simpeg_jenjang_pendidikan', 'simpeg_data_pendidikan_formal.jenjang_pendidikan_id', '=', 'simpeg_jenjang_pendidikan.id')
            ->whereIn('simpeg_data_pendidikan_formal.pegawai_id', $employeeIds)
            ->select(
                'simpeg_jenjang_pendidikan.jenjang_singkatan as kode',
                'simpeg_jenjang_pendidikan.jenjang_pendidikan',
                'simpeg_jenjang_pendidikan.urutan_jenjang_pendidikan',
                DB::raw('COUNT(*) as jumlah')
            )
            ->groupBy(
                'simpeg_jenjang_pendidikan.jenjang_singkatan',
                'simpeg_jenjang_pendidikan.jenjang_pendidikan',
                'simpeg_jenjang_pendidikan.urutan_jenjang_pendidikan'
            )
            ->orderBy('simpeg_jenjang_pendidikan.urutan_jenjang_pendidikan')
            ->get();

        // Dapatkan semua jenjang pendidikan yang ada di sistem
        $allEducationLevels = SimpegJenjangPendidikan::orderBy('urutan_jenjang_pendidikan')
            ->get(['jenjang_singkatan', 'jenjang_pendidikan', 'urutan_jenjang_pendidikan']);

        // Buat mapping untuk hasil yang sudah ada
        $educationCounts = $educationLevels->pluck('jumlah', 'kode')->toArray();

        // Buat dataset lengkap dengan nilai 0 untuk jenjang yang tidak ada data
        $completeData = [];
        $labels = [];
        $data = [];

        foreach ($allEducationLevels as $level) {
            $kode = $level->jenjang_singkatan;
            $labels[] = $kode;
            $count = isset($educationCounts[$kode]) ? $educationCounts[$kode] : 0;
            $data[] = $count;
            
            $completeData[] = [
                'kode' => $kode,
                'jenjang_pendidikan' => $kode . ' - ' . $level->jenjang_pendidikan,
                'jumlah' => $count
            ];
        }

        $total = array_sum($data);

        // Siapkan data chart
        $chartData = [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Jumlah Pegawai ' . $staffType,
                    'data' => $data
                ],
            ],
        ];

        // Siapkan data tabel
        $tableData = [
            'headers' => ['Kode', 'Jenjang Pendidikan', 'Jumlah'],
            'rows' => $completeData,
            'total' => $total,
        ];

        return [
            'chart_data' => $chartData,
            'table_data' => $tableData,
        ];
    }

    /**
     * Get all education distribution (for backward compatibility)
     */
    public function getEducationDistribution($unitKerjaId = null)
    {
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);
        
        // Ambil IDs pegawai
        $employeeIds = SimpegPegawai::whereIn('unit_kerja_id', $unitIds)
            ->pluck('id')
            ->toArray();

        // Jika tidak ada pegawai, kembalikan data kosong
        if (empty($employeeIds)) {
            return $this->getEmptyEducationDistribution();
        }

        // Dapatkan data distribusi pendidikan
        return $this->getEducationDistributionByEmployeeIds($employeeIds, '');
    }

    /**
     * Get news related to selected work unit
     */
    public function getNews($unitKerjaId = null, $limit = 5)
    {
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);
        
        // Periksa apakah berita menggunakan soft deletes
        $query = SimpegBerita::whereIn('unit_kerja_id', $unitIds);
        
        // Debug: check if we actually have news records
        $totalCount = (clone $query)->count();
        if ($totalCount == 0) {
            // If no news found with the filter, try getting all news
            $query = SimpegBerita::query();
            if ((clone $query)->count() == 0) {
                // Truly no news in the system
                return [];
            }
        }
        
        $news = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->select('id', 'judul', 'konten as ringkasan', 'gambar_berita as gambar', 
                     'tgl_posting as tanggal', 'unit_kerja_id', 'created_at')
            ->get()
            ->map(function ($item) {
                $unitKerja = SimpegUnitKerja::where('kode_unit', $item->unit_kerja_id)->first();
                $item->unit_kerja_nama = $unitKerja ? $unitKerja->nama_unit : null;
                
                // Potong konten jika terlalu panjang untuk ringkasan
                if (strlen($item->ringkasan) > 150) {
                    $item->ringkasan = substr(strip_tags($item->ringkasan), 0, 147) . '...';
                } else if ($item->ringkasan) {
                    $item->ringkasan = strip_tags($item->ringkasan);
                }
                
                return $item;
            });

        return $news;
    }

    /**
     * Get employee birthdays for current month
     */
  /**
 * Get employee birthdays for current month
 */
public function getCurrentMonthBirthdays($unitKerjaId = null)
{
    $unitIds = $this->getAllChildUnitIds($unitKerjaId);
    $currentMonth = date('m');
    
    // Gunakan DB::raw untuk query PostgreSQL yang benar
    $birthdays = DB::table('simpeg_pegawai')
        ->whereIn('unit_kerja_id', $unitIds)
        ->whereRaw('EXTRACT(MONTH FROM tanggal_lahir) = ?', [$currentMonth])
        ->orderByRaw('EXTRACT(DAY FROM tanggal_lahir)')
        ->select('id', 'nip', 'nama', 'tanggal_lahir', 'unit_kerja_id')
        ->get();
        
    // Map untuk menambahkan unit_kerja_nama
    $birthdays = collect($birthdays)->map(function ($item) {
        $unitKerja = DB::table('simpeg_unit_kerja')
            ->where('kode_unit', $item->unit_kerja_id)
            ->first();
            
        $item->unit_kerja_nama = $unitKerja ? $unitKerja->nama_unit : null;
        return $item;
    });

    return $birthdays;
}
}