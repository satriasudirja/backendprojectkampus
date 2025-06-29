<?php
// app/Http/Controllers/Api/PayrollController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\PayrollService;
use App\Models\PenggajianPeriode;
use App\Models\PenggajianPegawai;
use Illuminate\Support\Facades\Validator;
use Exception;

class PayrollController extends Controller
{
    /**
     * Endpoint untuk memicu proses pembuatan payroll untuk periode tertentu.
     * METHOD: POST
     * URL: /api/payroll/generate
     * BODY: { "tahun": 2025, "bulan": 6, "allowances": [...], "deductions": [...] }
     */
    public function generate(Request $request, PayrollService $payrollService)
    {
        $validator = Validator::make($request->all(), [
            'tahun' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'bulan' => 'required|integer|min:1|max:12',
            'allowances' => 'nullable|array', // Tunjangan tambahan
            'deductions' => 'nullable|array', // Potongan tambahan
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $periode = $payrollService->generatePayrollForPeriod(
                $request->tahun,
                $request->bulan,
                $request->input('allowances', []),
                $request->input('deductions', [])
            );
            return response()->json([
                'message' => 'Payroll generation completed successfully!',
                'data' => $periode
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Payroll generation failed.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint untuk melihat daftar semua periode payroll yang sudah dibuat.
     * METHOD: GET
     * URL: /api/payroll/periods?tahun=2025&bulan=7&per_page=25
     */
    public function indexPeriods(Request $request)
    {
        $query = PenggajianPeriode::query();

        // Filter berdasarkan tahun jika ada di request
        if ($request->has('tahun')) {
            $query->where('tahun', $request->input('tahun'));
        }

        // Filter berdasarkan bulan jika ada di request
        if ($request->has('bulan')) {
            $query->where('bulan', $request->input('bulan'));
        }

        // Validasi input per_page
        $allowedPerPages = [10, 25, 50, 100];
        $perPage = $request->input('per_page', 10); // Default ke 10
        if (!in_array($perPage, $allowedPerPages)) {
            $perPage = 10; // Jika tidak valid, kembalikan ke default
        }

        $periodes = $query->orderBy('tahun', 'desc')
                         ->orderBy('bulan', 'desc')
                         ->paginate($perPage); // Gunakan nilai per_page yang sudah divalidasi

        return response()->json($periodes);
    }

    /**
     * Endpoint untuk melihat detail satu periode payroll, termasuk daftar slip gaji.
     * METHOD: GET
     * URL: /api/payroll/periods/{id}
     */
    public function showPeriod(PenggajianPeriode $periode)
    {
        // Load relasi dengan hanya memilih kolom yang diperlukan dari pegawai
        $periode->load('penggajianPegawai.pegawai:id,nip,nama'); 
        return response()->json($periode);
    }

    /**
     * Endpoint untuk melihat detail slip gaji seorang pegawai pada periode tertentu.
     * METHOD: GET
     * URL: /api/payroll/slips/{slip}
     */
    public function showSlip(PenggajianPegawai $slip)
    {
        // Load semua relasi yang dibutuhkan untuk detail slip gaji
        $slip->load(['pegawai', 'periode', 'komponenPendapatan', 'komponenPotongan']);
        return response()->json($slip);
    }
}
