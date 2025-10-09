<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterPotonganWajib;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MasterPotonganWajibController extends Controller
{
    /**
     * GET /api/master-potongan-wajib
     * List semua master potongan dengan pagination dan filter
     */
    public function index(Request $request)
    {
        $query = MasterPotonganWajib::query();

        // Filter berdasarkan status aktif
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter berdasarkan jenis potongan
        if ($request->has('jenis_potongan')) {
            $query->where('jenis_potongan', $request->input('jenis_potongan'));
        }

        // Filter berdasarkan dihitung dari
        if ($request->has('dihitung_dari')) {
            $query->where('dihitung_dari', $request->input('dihitung_dari'));
        }

        // Search berdasarkan nama atau kode
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('kode_potongan', 'LIKE', "%{$search}%")
                  ->orWhere('nama_potongan', 'LIKE', "%{$search}%");
            });
        }

        $perPage = in_array($request->input('per_page'), [10, 25, 50, 100]) 
                   ? $request->input('per_page') 
                   : 10;

        $potonganList = $query->orderBy('kode_potongan')->paginate($perPage);

        return response()->json($potonganList);
    }

    /**
     * POST /api/master-potongan-wajib
     * Create master potongan baru
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode_potongan' => 'required|string|max:50|unique:master_potongan_wajib,kode_potongan',
            'nama_potongan' => 'required|string|max:200',
            'jenis_potongan' => 'required|in:persen,nominal',
            'nilai_potongan' => 'required|numeric|min:0',
            'dihitung_dari' => 'required|in:gaji_pokok,penghasilan_bruto',
            'is_active' => 'boolean',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $potongan = MasterPotonganWajib::create($request->all());

        return response()->json([
            'message' => 'Master potongan wajib berhasil dibuat',
            'data' => $potongan
        ], 201);
    }

    /**
     * GET /api/master-potongan-wajib/{id}
     * Detail satu master potongan
     */
    public function show(MasterPotonganWajib $masterPotonganWajib)
    {
        return response()->json($masterPotonganWajib);
    }

    /**
     * PUT/PATCH /api/master-potongan-wajib/{id}
     * Update master potongan
     */
    public function update(Request $request, MasterPotonganWajib $masterPotonganWajib)
    {
        $validator = Validator::make($request->all(), [
            'kode_potongan' => 'sometimes|string|max:50|unique:master_potongan_wajib,kode_potongan,' . $masterPotonganWajib->id,
            'nama_potongan' => 'sometimes|string|max:200',
            'jenis_potongan' => 'sometimes|in:persen,nominal',
            'nilai_potongan' => 'sometimes|numeric|min:0',
            'dihitung_dari' => 'sometimes|in:gaji_pokok,penghasilan_bruto',
            'is_active' => 'boolean',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $masterPotonganWajib->update($request->all());

        return response()->json([
            'message' => 'Master potongan wajib berhasil diupdate',
            'data' => $masterPotonganWajib
        ]);
    }

    /**
     * DELETE /api/master-potongan-wajib/{id}
     * Hapus master potongan (soft delete dengan ubah is_active = false lebih aman)
     */
    public function destroy(MasterPotonganWajib $masterPotonganWajib)
    {
        // Lebih aman non-aktifkan daripada hapus permanen
        $masterPotonganWajib->update(['is_active' => false]);

        return response()->json([
            'message' => 'Master potongan wajib berhasil dinonaktifkan'
        ]);
    }

    /**
     * POST /api/master-potongan-wajib/{id}/toggle-status
     * Toggle active/inactive status
     */
    public function toggleStatus(MasterPotonganWajib $masterPotonganWajib)
    {
        $masterPotonganWajib->update(['is_active' => !$masterPotonganWajib->is_active]);

        return response()->json([
            'message' => 'Status berhasil diubah',
            'data' => $masterPotonganWajib
        ]);
    }
}