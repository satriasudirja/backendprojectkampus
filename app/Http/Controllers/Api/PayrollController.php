<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\PayrollService;
use App\Models\PenggajianPeriode;
use App\Models\PenggajianPegawai;
use Illuminate\Support\Facades\Validator;
use Exception;
use Barryvdh\DomPDF\Facade\Pdf; // Import PDF Facade

class PayrollController extends Controller
{
    /**
     * Generate payroll untuk periode tertentu
     */
    public function generate(Request $request, PayrollService $payrollService)
    {
        $validator = Validator::make($request->all(), [
            'tahun' => 'required|integer|min:2020|max:' . (date('Y') + 1),
            'bulan' => 'required|integer|min:1|max:12',
            'allowances' => 'nullable|array',
            'deductions' => 'nullable|array',
            'excluded_pegawai_ids' => 'nullable|array',
            'excluded_pegawai_ids.*' => 'string|exists:simpeg_pegawai,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $periode = $payrollService->generatePayrollForPeriod(
                $request->tahun,
                $request->bulan,
                $request->input('allowances', []),
                $request->input('deductions', []),
                $request->input('excluded_pegawai_ids', [])
            );
            
            $excludedCount = count($request->input('excluded_pegawai_ids', []));
            $message = 'Payroll generation completed successfully!';
            if ($excludedCount > 0) {
                $message .= " ({$excludedCount} pegawai dikecualikan)";
            }
            
            return response()->json([
                'message' => $message,
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
     * List semua periode payroll
     */
    public function indexPeriods(Request $request)
    {
        $query = PenggajianPeriode::query();

        if ($request->has('tahun')) {
            $query->where('tahun', $request->input('tahun'));
        }

        if ($request->has('bulan')) {
            $query->where('bulan', $request->input('bulan'));
        }

        $allowedPerPages = [10, 25, 50, 100];
        $perPage = $request->input('per_page', 10);
        if (!in_array($perPage, $allowedPerPages)) {
            $perPage = 10;
        }

        $periodes = $query->orderBy('tahun', 'desc')
                         ->orderBy('bulan', 'desc')
                         ->paginate($perPage);

        return response()->json($periodes);
    }

    /**
     * Detail satu periode payroll
     */
    public function showPeriod(PenggajianPeriode $periode)
    {
        $periode->load('penggajianPegawai.pegawai:id,nip,nama');
        return response()->json($periode);
    }

    /**
     * Detail slip gaji satu pegawai
     */
    public function showSlip(PenggajianPegawai $slip)
    {
        $slip->load(['pegawai', 'periode', 'komponenPendapatan', 'komponenPotongan']);
        return response()->json($slip);
    }

    /**
     * Cetak slip gaji individual (PDF atau HTML)
     * GET /api/payroll/slips/{slip}/print?format=pdf
     */
    public function printSlip(PenggajianPegawai $slip, Request $request)
    {
        $slip->load(['pegawai', 'periode', 'komponenPendapatan', 'komponenPotongan']);
        
        $format = $request->input('format', 'html');
        
        if ($format === 'pdf') {
            $pdf = Pdf::loadView('payroll.slip-gaji', compact('slip'));
            $pdf->setPaper('a4', 'portrait');
            
            $filename = 'slip-gaji-' . $slip->pegawai->nip . '-' . 
                        $slip->periode->tahun . '-' . $slip->periode->bulan . '.pdf';
            
            return $pdf->download($filename);
        }
        
        // Return HTML untuk preview
        return view('payroll.slip-gaji', compact('slip'));
    }

    /**
     * Cetak slip gaji bulk (semua pegawai dalam periode)
     * GET /api/payroll/periods/{periode}/print-bulk?format=pdf
     */
    public function printBulkSlips(PenggajianPeriode $periode, Request $request)
    {
        $periode->load([
            'penggajianPegawai.pegawai',
            'penggajianPegawai.komponenPendapatan',
            'penggajianPegawai.komponenPotongan'
        ]);
        
        if ($periode->penggajianPegawai->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada slip gaji untuk periode ini.'
            ], 404);
        }
        
        $format = $request->input('format', 'html');
        
        if ($format === 'pdf') {
            $slips = $periode->penggajianPegawai;
            
            $pdf = Pdf::loadView('payroll.bulk-slip-gaji', compact('slips', 'periode'));
            $pdf->setPaper('a4', 'portrait');
            
            $filename = 'slip-gaji-bulk-' . $periode->tahun . '-' . $periode->bulan . '.pdf';
            
            return $pdf->download($filename);
        }
        
        return view('payroll.bulk-slip-gaji', compact('periode'));
    }

    /**
     * Cetak slip gaji custom (pilih pegawai tertentu)
     * POST /api/payroll/print-selected
     * BODY: { "slip_ids": ["uuid1", "uuid2"], "format": "pdf" }
     */
    public function printSelectedSlips(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slip_ids' => 'required|array|min:1',
            'slip_ids.*' => 'exists:penggajian_pegawai,id',
            'format' => 'nullable|in:html,pdf'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $slips = PenggajianPegawai::whereIn('id', $request->slip_ids)
            ->with(['pegawai', 'periode', 'komponenPendapatan', 'komponenPotongan'])
            ->get();

        if ($slips->isEmpty()) {
            return response()->json(['message' => 'Slip gaji tidak ditemukan'], 404);
        }

        $format = $request->input('format', 'html');
        
        // Ambil periode dari slip pertama (asumsi semua dari periode yang sama)
        $periode = $slips->first()->periode;

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('payroll.bulk-slip-gaji', compact('slips', 'periode'));
            $pdf->setPaper('a4', 'portrait');
            
            $filename = 'slip-gaji-selected-' . now()->format('YmdHis') . '.pdf';
            
            return $pdf->download($filename);
        }

        return view('payroll.bulk-slip-gaji', compact('slips', 'periode'));
    }
}