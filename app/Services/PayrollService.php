<?php
namespace App\Services;

use App\Models\SimpegPegawai;
use App\Models\SimpegDataPangkat;
use App\Models\SimpegDataJabatanStruktural;
use App\Models\SimpegDataJabatanFungsional;
use App\Models\PenggajianPeriode;
use App\Models\PenggajianPegawai;
use App\Models\MasterPotonganWajib;
use App\Models\SimpegAbsensiRecord;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class PayrollService
{
    public function generatePayrollForPeriod(
        int $tahun, 
        int $bulan, 
        array $additionalAllowances = [], 
        array $additionalDeductions = [],
        array $excludedPegawaiIds = [] // TAMBAHAN: Array pegawai yang dikecualikan
    ): PenggajianPeriode {
        return DB::transaction(function () use ($tahun, $bulan, $additionalAllowances, $additionalDeductions, $excludedPegawaiIds) {
            $namaPeriode = "Penggajian " . Carbon::create($tahun, $bulan)->locale('id')->monthName . " " . $tahun;
            
            $periode = PenggajianPeriode::where('tahun', $tahun)->where('bulan', $bulan)->first();

            if ($periode && $periode->status === 'completed') {
                throw new Exception("Payroll untuk periode {$namaPeriode} sudah selesai dan tidak bisa diubah.");
            }

            if (!$periode) {
                $periode = PenggajianPeriode::create([
                    'tahun' => $tahun, 
                    'bulan' => $bulan, 
                    'nama_periode' => $namaPeriode, 
                    'status' => 'processing'
                ]);
            } else {
                $periode->update(['status' => 'processing']);
            }
            
            $periode->penggajianPegawai()->delete();
            
            // Query pegawai aktif dengan filter exclusion
            $pegawaisQuery = SimpegPegawai::whereHas('statusAktif', function ($query) {
                $query->where('kode', 'AA');
            });

            // FILTER: Kecualikan pegawai yang ada di excludedPegawaiIds
            if (!empty($excludedPegawaiIds)) {
                $pegawaisQuery->whereNotIn('id', $excludedPegawaiIds);
            }

            $pegawais = $pegawaisQuery->get();

            if ($pegawais->isEmpty()) {
                throw new Exception("Tidak ditemukan pegawai aktif untuk diproses.");
            }

            foreach ($pegawais as $pegawai) {
                $this->processEmployeePayroll($periode, $pegawai, $additionalAllowances, $additionalDeductions);
            }

            $periode->status = 'completed';
            $periode->save();

            return $periode->load('penggajianPegawai');
        });
    }

    private function processEmployeePayroll(
        PenggajianPeriode $periode, 
        SimpegPegawai $pegawai, 
        array $additionalAllowances, 
        array $additionalDeductions
    ) {
        $pendapatan = [];
        $potongan = [];
        $gajiPokok = 0;

        // 1. GAJI POKOK dari Pangkat
        $dataPangkatAktif = SimpegDataPangkat::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'disetujui')
            ->orderBy('tgl_disetujui', 'desc')
            ->with('pangkat')
            ->first();
            
        if ($dataPangkatAktif && $dataPangkatAktif->pangkat) {
            $gajiPokok = $dataPangkatAktif->pangkat->tunjangan ?? 0;
            $pendapatan[] = [
                'kode_komponen' => 'GAPOK', 
                'deskripsi' => 'Gaji Pokok - ' . $dataPangkatAktif->pangkat->pangkat, 
                'nominal' => $gajiPokok
            ];
        }

        // 2. TUNJANGAN STRUKTURAL
        $dataJabatanStrukturalAktif = SimpegDataJabatanStruktural::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'disetujui')
            ->where(function ($q) {
                $q->whereNull('tgl_selesai')
                ->orWhere('tgl_selesai', '>', now());
            })
            ->orderBy('tgl_disetujui', 'desc')
            ->with('jabatanStruktural')
            ->first();
            
        if ($dataJabatanStrukturalAktif && $dataJabatanStrukturalAktif->jabatanStruktural) {
            $pendapatan[] = [
                'kode_komponen' => 'TUNJ_STRUKTURAL', 
                'deskripsi' => 'Tunjangan Jabatan Struktural - ' . $dataJabatanStrukturalAktif->jabatanStruktural->singkatan, 
                'nominal' => $dataJabatanStrukturalAktif->jabatanStruktural->tunjangan ?? 0
            ];
        }

        // 3. TUNJANGAN FUNGSIONAL
        $dataJabatanFungsionalAktif = SimpegDataJabatanFungsional::where('pegawai_id', $pegawai->id)
            ->where('status_pengajuan', 'disetujui')
            ->orderBy('tgl_disetujui', 'desc')
            ->with('jabatanFungsional')
            ->first();
            
        if ($dataJabatanFungsionalAktif && $dataJabatanFungsionalAktif->jabatanFungsional) {
            $pendapatan[] = [
                'kode_komponen' => 'TUNJ_FUNGSIONAL', 
                'deskripsi' => 'Tunjangan Jabatan Fungsional - ' . $dataJabatanFungsionalAktif->jabatanFungsional->nama_jabatan_fungsional, 
                'nominal' => $dataJabatanFungsionalAktif->jabatanFungsional->tunjangan ?? 0
            ];
        }
        
        // 4. TUNJANGAN TAMBAHAN dari parameter
        $pegawaiAllowances = array_filter($additionalAllowances, fn($item) => $item['pegawai_id'] == $pegawai->id);
        foreach ($pegawaiAllowances as $allowance) {
            $pendapatan[] = [
                'kode_komponen' => $allowance['kode'], 
                'deskripsi' => $allowance['deskripsi'], 
                'nominal' => $allowance['nominal'] ?? 0
            ];
        }

        // 5. HITUNG PENGHASILAN BRUTO (Total Pendapatan)
        $penghasilanBruto = array_sum(array_column($pendapatan, 'nominal'));

        // 6. POTONGAN WAJIB DINAMIS dari Master
        $potonganWajibList = MasterPotonganWajib::active()->get();
        
        foreach ($potonganWajibList as $masterPotongan) {
            $nominalPotongan = $masterPotongan->hitungPotongan($gajiPokok, $penghasilanBruto);
            
            $potongan[] = [
                'kode_komponen' => $masterPotongan->kode_potongan,
                'deskripsi' => $masterPotongan->nama_potongan . 
                              ($masterPotongan->jenis_potongan === 'persen' 
                                ? " ({$masterPotongan->nilai_potongan}%)" 
                                : ''),
                'nominal' => $nominalPotongan
            ];
        }

        if ($dataPangkatAktif && $dataPangkatAktif->pangkat && $dataPangkatAktif->pangkat->potongan > 0) {
            // Hitung jumlah Alpha di bulan periode penggajian
            $jumlahAlpha = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)
                ->whereYear('tanggal_absensi', $periode->tahun)
                ->whereMonth('tanggal_absensi', $periode->bulan)
                ->alphaOnly()
                ->count();

            if ($jumlahAlpha > 0) {
                $potonganPerAlpha = $dataPangkatAktif->pangkat->potongan;
                $totalPotonganAlpha = $jumlahAlpha * $potonganPerAlpha;

                $potongan[] = [
                    'kode_komponen' => 'POT_ALPHA',
                    'deskripsi' => "Potongan Alpha ({$jumlahAlpha} hari × Rp " . number_format($potonganPerAlpha, 0, ',', '.') . ")",
                    'nominal' => $totalPotonganAlpha
                ];
            }
        }
        
        // 7. POTONGAN TAMBAHAN dari parameter (misalnya: pinjaman, denda, dll)
        $pegawaiDeductions = array_filter($additionalDeductions, fn($item) => $item['pegawai_id'] == $pegawai->id);
        foreach ($pegawaiDeductions as $deduction) {
            $potongan[] = [
                'kode_komponen' => $deduction['kode'], 
                'deskripsi' => $deduction['deskripsi'], 
                'nominal' => $deduction['nominal'] ?? 0
            ];
        }

        // 8. HITUNG TOTAL
        $totalPendapatan = array_sum(array_column($pendapatan, 'nominal'));
        $totalPotongan = array_sum(array_column($potongan, 'nominal'));
        $gajiBersih = $totalPendapatan - $totalPotongan;

        // 9. SIMPAN SLIP GAJI
        if ($totalPendapatan > 0) {
            $slipGaji = PenggajianPegawai::create([
                'periode_id' => $periode->id, 
                'pegawai_id' => $pegawai->id, 
                'total_pendapatan' => $totalPendapatan, 
                'total_potongan' => $totalPotongan, 
                'gaji_bersih' => $gajiBersih
            ]);
            
            if (!empty($pendapatan)) {
                $slipGaji->komponenPendapatan()->createMany($pendapatan);
            }
            
            if (!empty($potongan)) {
                $slipGaji->komponenPotongan()->createMany($potongan);
            }
        }
    }
}