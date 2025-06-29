<?php
namespace App\Services;

use App\Models\SimpegPegawai;
use App\Models\SimpegDataPangkat;
use App\Models\SimpegDataJabatanStruktural;
use App\Models\SimpegDataJabatanFungsional;
use App\Models\PenggajianPeriode;
use App\Models\PenggajianPegawai;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class PayrollService
{
    public function generatePayrollForPeriod(int $tahun, int $bulan, array $additionalAllowances = [], array $additionalDeductions = []): PenggajianPeriode
    {
        return DB::transaction(function () use ($tahun, $bulan, $additionalAllowances, $additionalDeductions) {
            $namaPeriode = "Penggajian " . Carbon::create($tahun, $bulan)->locale('id')->monthName . " " . $tahun;
            
            $periode = PenggajianPeriode::where('tahun', $tahun)->where('bulan', $bulan)->first();

            if ($periode && $periode->status === 'completed') {
                throw new Exception("Payroll untuk periode {$namaPeriode} sudah selesai dan tidak bisa diubah.");
            }

            if (!$periode) {
                $periode = PenggajianPeriode::create(['tahun' => $tahun, 'bulan' => $bulan, 'nama_periode' => $namaPeriode, 'status' => 'processing']);
            } else {
                $periode->update(['status' => 'processing']);
            }
            
            $periode->penggajianPegawai()->delete();
            $pegawais = SimpegPegawai::where('status_aktif_id', 1)->get();

            if($pegawais->isEmpty()){
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

    private function processEmployeePayroll(PenggajianPeriode $periode, SimpegPegawai $pegawai, array $additionalAllowances, array $additionalDeductions)
    {
        $pendapatan = [];
        $potongan = [];

        $dataPangkatAktif = SimpegDataPangkat::where('pegawai_id', $pegawai->id)->where('status_pengajuan', 'disetujui')->orderBy('tgl_disetujui', 'desc')->with('pangkat')->first();
        if ($dataPangkatAktif && $dataPangkatAktif->pangkat) {
            $pendapatan[] = ['kode_komponen' => 'GAPOK', 'deskripsi' => 'Gaji Pokok - ' . $dataPangkatAktif->pangkat->pangkat, 'nominal' => $dataPangkatAktif->pangkat->tunjangan ?? 0];
        }

        $dataJabatanStrukturalAktif = SimpegDataJabatanStruktural::where('pegawai_id', $pegawai->id)->where('status_pengajuan', 'disetujui')->whereNull('tgl_selesai')->orderBy('tgl_disetujui', 'desc')->with('jabatanStruktural')->first();
        if ($dataJabatanStrukturalAktif && $dataJabatanStrukturalAktif->jabatanStruktural) {
            // FIX: Menggunakan null coalescing operator (??) untuk memastikan nilai nominal tidak null.
            $pendapatan[] = ['kode_komponen' => 'TUNJ_STRUKTURAL', 'deskripsi' => 'Tunjangan Jabatan Struktural - ' . $dataJabatanStrukturalAktif->jabatanStruktural->kode, 'nominal' => $dataJabatanStrukturalAktif->jabatanStruktural->tunjangan ?? 0];
        }

        $dataJabatanFungsionalAktif = SimpegDataJabatanFungsional::where('pegawai_id', $pegawai->id)->where('status_pengajuan', 'disetujui')->orderBy('tgl_disetujui', 'desc')->with('jabatanFungsional')->first();
        if ($dataJabatanFungsionalAktif && $dataJabatanFungsionalAktif->jabatanFungsional) {
            // FIX: Menggunakan null coalescing operator (??) untuk memastikan nilai nominal tidak null.
            $pendapatan[] = ['kode_komponen' => 'TUNJ_FUNGSIONAL', 'deskripsi' => 'Tunjangan Jabatan Fungsional - ' . $dataJabatanFungsionalAktif->jabatanFungsional->nama_jabatan_fungsional, 'nominal' => $dataJabatanFungsionalAktif->jabatanFungsional->tunjangan ?? 0];
        }
        
        $pegawaiAllowances = array_filter($additionalAllowances, fn($item) => $item['pegawai_id'] == $pegawai->id);
        foreach($pegawaiAllowances as $allowance) {
             $pendapatan[] = ['kode_komponen' => $allowance['kode'], 'deskripsi' => $allowance['deskripsi'], 'nominal' => $allowance['nominal'] ?? 0];
        }

        $totalPendapatanKotor = array_sum(array_column($pendapatan, 'nominal'));
        $potongan[] = ['kode_komponen' => 'BPJS_KES', 'deskripsi' => 'Potongan BPJS Kesehatan (1%)', 'nominal' => $totalPendapatanKotor * 0.01];
        
        $pegawaiDeductions = array_filter($additionalDeductions, fn($item) => $item['pegawai_id'] == $pegawai->id);
        foreach($pegawaiDeductions as $deduction) {
             $potongan[] = ['kode_komponen' => $deduction['kode'], 'deskripsi' => $deduction['deskripsi'], 'nominal' => $deduction['nominal'] ?? 0];
        }

        $totalPendapatan = array_sum(array_column($pendapatan, 'nominal'));
        $totalPotongan = array_sum(array_column($potongan, 'nominal'));

        if ($totalPendapatan > 0) {
            $slipGaji = PenggajianPegawai::create(['periode_id' => $periode->id, 'pegawai_id' => $pegawai->id, 'total_pendapatan' => $totalPendapatan, 'total_potongan' => $totalPotongan, 'gaji_bersih' => $totalPendapatan - $totalPotongan]);
            if(!empty($pendapatan)) $slipGaji->komponenPendapatan()->createMany($pendapatan);
            if(!empty($potongan)) $slipGaji->komponenPotongan()->createMany($potongan);
        }
    }
}