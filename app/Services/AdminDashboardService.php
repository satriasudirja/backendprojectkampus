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
     * =================================================================
     * FUNGSI DIPERBAIKI: Mengambil semua ID unit turunan secara rekursif.
     * =================================================================
     *
     * @param string|null $unitKerjaCode Kode unit dari unit induk.
     * @return array Array berisi ID integer dari unit induk dan semua turunannya.
     */
    protected function getAllChildUnitIds($unitKerjaCode = null)
    {
        // Jika tidak ada kode unit yang diberikan, kembalikan semua ID unit kerja.
        if (!$unitKerjaCode) {
            return SimpegUnitKerja::pluck('id')->toArray();
        }

        // Cari unit induk berdasarkan KODE-nya.
        $parentUnit = SimpegUnitKerja::where('kode_unit', $unitKerjaCode)->first();
        
        // Jika unit induk tidak ditemukan, kembalikan array kosong.
        if (!$parentUnit) {
            return [];
        }

        // Mulai proses rekursif dengan ID integer dari unit induk.
        $allChildIds = [$parentUnit->id];
        $idsToProcess = [$parentUnit->id];

        while (!empty($idsToProcess)) {
            // Cari semua anak yang memiliki parent_unit_id dari antrian.
            // Ini sekarang membandingkan integer (parent_unit_id) dengan integer (id).
            $children = SimpegUnitKerja::whereIn('parent_unit_id', $idsToProcess)->get(['id']);
            
            $idsToProcess = [];

            foreach ($children as $child) {
                if (!in_array($child->id, $allChildIds)) {
                    $allChildIds[] = $child->id;
                    $idsToProcess[] = $child->id;
                }
            }
        }

        return array_unique($allChildIds);
    }

    /**
     * Get staff summary data
     */
    public function getStaffSummary($unitKerjaId = null)
    {
        // Parameter 'unitKerjaId' dari controller sebenarnya adalah kode_unit.
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);

        // Jika tidak ada unit yang cocok, kembalikan data kosong untuk menghindari error.
        if (empty($unitIds)) {
            return ['active_employees' => 0, 'inactive_employees' => 0, 'academic_staff' => 0, 'non_academic_staff' => 0];
        }

        // Query dasar untuk pegawai di unit yang dipilih (sekarang menggunakan ID integer)
        $query = SimpegPegawai::whereIn('unit_kerja_id', $unitIds);

        // Pegawai aktif
        $activeEmployees = (clone $query)
            ->whereHas('statusAktif', function ($q) {
                $q->where('status_keluar', false);
            })->count();

        // Pegawai tidak aktif
        $inactiveEmployees = (clone $query)
            ->whereHas('statusAktif', function ($q) {
                $q->where('status_keluar', true);
            })->count();

        // Staf akademik
        $academicStaff = (clone $query)
            ->whereHas('jabatanAkademik.role', function ($q) {
                $q->whereIn('nama', ['Dosen', 'Dosen Praktisi/Industri']);
            })->count();

        // Staf non-akademik
        $nonAcademicStaff = (clone $query)
             ->whereHas('jabatanAkademik.role', function ($q) {
                $q->where('nama', 'Tenaga Kependidikan');
            })->count();

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
        if (empty($unitIds)) {
            return $this->getEmptyEducationDistribution(); // Kembalikan struktur kosong
        }
        
        $baseQuery = SimpegPegawai::whereIn('unit_kerja_id', $unitIds);

        $academicStaff = (clone $baseQuery)->whereHas('jabatanAkademik.role', function ($q) {
            $q->whereIn('nama', ['Dosen', 'Dosen Praktisi/Industri']);
        })->count();

        $nonAcademicStaff = (clone $baseQuery)->whereHas('jabatanAkademik.role', function ($q) {
            $q->where('nama', 'Tenaga Kependidikan');
        })->count();

        $totalStaff = $academicStaff + $nonAcademicStaff;
        
        $academicPercentage = $totalStaff > 0 ? round(($academicStaff / $totalStaff) * 100, 2) : 0;
        $nonAcademicPercentage = $totalStaff > 0 ? round(($nonAcademicStaff / $totalStaff) * 100, 2) : 0;

        $chartData = [
            'labels' => ['Akademik', 'Non Akademik'],
            'datasets' => [['data' => [$academicStaff, $nonAcademicStaff]]],
        ];

        $tableData = [
            'headers' => ['No', 'Fungsional', 'Jumlah'],
            'rows' => [[1, 'Akademik', $academicStaff], [2, 'Non Akademik', $nonAcademicStaff]],
            'total' => $totalStaff,
        ];

        return [
            'chart_data' => $chartData,
            'table_data' => $tableData,
            'percentages' => ['academic' => $academicPercentage, 'non_academic' => $nonAcademicPercentage]
        ];
    }

    /**
     * Get work relationship distribution
     */
    public function getWorkRelationships($unitKerjaId = null)
    {
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);
        $employeeIds = SimpegPegawai::whereIn('unit_kerja_id', $unitIds)->pluck('id')->toArray();

        if (empty($employeeIds)) {
            return [
                'chart_data' => ['labels' => [], 'datasets' => [['label' => 'Jumlah Pegawai', 'data' => []]]],
                'table_data' => ['headers' => ['Kode', 'Hubungan Kerja', 'Jumlah'], 'rows' => [], 'total' => 0],
            ];
        }

        $workRelationships = DB::table('simpeg_data_hubungan_kerja')
            ->join('simpeg_hubungan_kerja', 'simpeg_data_hubungan_kerja.hubungan_kerja_id', '=', 'simpeg_hubungan_kerja.id')
            ->whereIn('simpeg_data_hubungan_kerja.pegawai_id', $employeeIds)
            ->select('simpeg_hubungan_kerja.kode', 'simpeg_hubungan_kerja.nama_hub_kerja as hubungan_kerja', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('simpeg_hubungan_kerja.kode', 'simpeg_hubungan_kerja.nama_hub_kerja')
            ->orderBy('simpeg_hubungan_kerja.kode')->get();

        $total = $workRelationships->sum('jumlah');
        $chartData = [
            'labels' => $workRelationships->pluck('kode')->toArray(),
            'datasets' => [['label' => 'Jumlah Pegawai', 'data' => $workRelationships->pluck('jumlah')->toArray()]],
        ];

        $tableRows = $workRelationships->map(function ($item) {
            return ['kode' => $item->kode, 'hubungan_kerja' => $item->hubungan_kerja, 'jumlah' => $item->jumlah];
        })->toArray();
        
        $tableData = ['headers' => ['Kode', 'Hubungan Kerja', 'Jumlah'], 'rows' => $tableRows, 'total' => $total];

        return ['chart_data' => $chartData, 'table_data' => $tableData];
    }

    /**
     * Get academic education distribution
     */
    public function getAcademicEducationDistribution($unitKerjaId = null)
    {
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);
        $academicEmployeeIds = SimpegPegawai::whereIn('unit_kerja_id', $unitIds)
            ->whereHas('jabatanAkademik.role', function ($q) {
                $q->whereIn('nama', ['Dosen', 'Dosen Praktisi/Industri']);
            })->pluck('simpeg_pegawai.id')->toArray();

        if (empty($academicEmployeeIds)) {
            return $this->getEmptyEducationDistribution();
        }
        return $this->getEducationDistributionByEmployeeIds($academicEmployeeIds, 'Akademik');
    }

    /**
     * Get non-academic education distribution
     */
    public function getNonAcademicEducationDistribution($unitKerjaId = null)
    {
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);
        $nonAcademicEmployeeIds = SimpegPegawai::whereIn('unit_kerja_id', $unitIds)
            ->whereHas('jabatanAkademik.role', function ($q) {
                $q->where('nama', 'Tenaga Kependidikan');
            })->pluck('simpeg_pegawai.id')->toArray();

        if (empty($nonAcademicEmployeeIds)) {
            return $this->getEmptyEducationDistribution();
        }
        return $this->getEducationDistributionByEmployeeIds($nonAcademicEmployeeIds, 'Non-Akademik');
    }

    private function getEmptyEducationDistribution()
    {
        return [
            'chart_data' => ['labels' => [], 'datasets' => [['label' => 'Jumlah Pegawai', 'data' => []]]],
            'table_data' => ['headers' => ['Kode', 'Jenjang Pendidikan', 'Jumlah'], 'rows' => [], 'total' => 0],
        ];
    }

    private function getEducationDistributionByEmployeeIds($employeeIds, $staffType = '')
    {
        $educationLevels = DB::table('simpeg_data_pendidikan_formal')
            ->join('simpeg_jenjang_pendidikan', 'simpeg_data_pendidikan_formal.jenjang_pendidikan_id', '=', 'simpeg_jenjang_pendidikan.id')
            ->whereIn('simpeg_data_pendidikan_formal.pegawai_id', $employeeIds)
            ->select('simpeg_jenjang_pendidikan.jenjang_singkatan as kode', 'simpeg_jenjang_pendidikan.jenjang_pendidikan', 'simpeg_jenjang_pendidikan.urutan_jenjang_pendidikan', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('simpeg_jenjang_pendidikan.jenjang_singkatan', 'simpeg_jenjang_pendidikan.jenjang_pendidikan', 'simpeg_jenjang_pendidikan.urutan_jenjang_pendidikan')
            ->orderBy('simpeg_jenjang_pendidikan.urutan_jenjang_pendidikan')->get();

        $allEducationLevels = SimpegJenjangPendidikan::orderBy('urutan_jenjang_pendidikan')->get(['jenjang_singkatan', 'jenjang_pendidikan']);
        $educationCounts = $educationLevels->pluck('jumlah', 'kode')->toArray();
        
        $labels = [];
        $data = [];
        $tableRows = [];

        foreach ($allEducationLevels as $level) {
            $kode = $level->jenjang_singkatan;
            $count = $educationCounts[$kode] ?? 0;
            $labels[] = $kode;
            $data[] = $count;
            $tableRows[] = ['kode' => $kode, 'jenjang_pendidikan' => $kode . ' - ' . $level->jenjang_pendidikan, 'jumlah' => $count];
        }

        $chartData = ['labels' => $labels, 'datasets' => [['label' => 'Jumlah Pegawai ' . $staffType, 'data' => $data]]];
        $tableData = ['headers' => ['Kode', 'Jenjang Pendidikan', 'Jumlah'], 'rows' => $tableRows, 'total' => array_sum($data)];

        return ['chart_data' => $chartData, 'table_data' => $tableData];
    }

    public function getEducationDistribution($unitKerjaId = null)
    {
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);
        $employeeIds = SimpegPegawai::whereIn('unit_kerja_id', $unitIds)->pluck('id')->toArray();
        if (empty($employeeIds)) {
            return $this->getEmptyEducationDistribution();
        }
        return $this->getEducationDistributionByEmployeeIds($employeeIds, '');
    }

    public function getNews($unitKerjaId = null, $limit = 5)
    {
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);
        $query = SimpegBerita::query();
        if (!empty($unitIds)) {
            // Filter berita berdasarkan unit kerja jika ada
            $query->whereIn('unit_kerja_id', $unitIds);
        }

        return $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->select('id', 'judul', 'konten as ringkasan', 'gambar_berita as gambar', 'tgl_posting as tanggal', 'created_at')
            ->get()->map(function ($item) {
                $item->ringkasan = substr(strip_tags($item->ringkasan), 0, 147) . (strlen(strip_tags($item->ringkasan)) > 150 ? '...' : '');
                return $item;
            });
    }

    public function getCurrentMonthBirthdays($unitKerjaId = null)
    {
        $unitIds = $this->getAllChildUnitIds($unitKerjaId);
        if (empty($unitIds)) {
            return [];
        }
        
        $currentMonth = date('m');
        return DB::table('simpeg_pegawai')
            ->leftJoin('simpeg_unit_kerja', 'simpeg_pegawai.unit_kerja_id', '=', 'simpeg_unit_kerja.id')
            ->whereIn('simpeg_pegawai.unit_kerja_id', $unitIds)
            ->whereRaw('EXTRACT(MONTH FROM tanggal_lahir) = ?', [$currentMonth])
            ->orderByRaw('EXTRACT(DAY FROM tanggal_lahir)')
            ->select('simpeg_pegawai.id', 'simpeg_pegawai.nip', 'simpeg_pegawai.nama', 'simpeg_pegawai.tanggal_lahir', 'simpeg_unit_kerja.nama_unit as unit_kerja_nama')
            ->get();
    }
}
