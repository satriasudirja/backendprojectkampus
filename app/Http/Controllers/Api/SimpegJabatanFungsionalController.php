<?php

namespace App\Http\Controllers\Api; 

use App\Http\Controllers\Controller; 
use App\Models\SimpegJabatanFungsional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SimpegJabatanFungsionalController extends Controller
{
    /**
     * Menampilkan daftar Jabatan Fungsional dengan paginasi dan pencarian.
     * Menggunakan Eager Loading untuk efisiensi query.
     */
    public function index(Request $request)
    {
        $query = SimpegJabatanFungsional::with(['pangkat']);

        // Fitur pencarian berdasarkan nama
        if ($request->has('search')) {
            $query->where('nama_jabatan_fungsional', 'like', '%' . $request->search . '%');
        }
        
        // Filter untuk data yang di-soft delete
        if ($request->has('trashed')) {
            $query->onlyTrashed();
        }

        $data = $query->orderBy('kode')->paginate(10);
        return response()->json($data);
    }

    /**
     * Menyimpan data baru.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pangkat_id' => 'required|uuid|exists:simpeg_master_pangkat,id',
            'kode' => 'required|string|max:5|unique:simpeg_jabatan_fungsional,kode',
            'nama_jabatan_fungsional' => 'required|string|max:30',
            'pangkat' => 'required|string|max:10',
            'angka_kredit' => 'required|string|max:6',
            'usia_pensiun' => 'required|integer',
            'keterangan' => 'nullable|string',
            'tunjangan' => 'nullable|numeric', // Tambahkan validasi tunjangan
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // FIX: Hanya ambil data yang divalidasi dan ada di fillable model
        // Ini lebih aman dan mencegah field 'id' ikut masuk jika tidak sengaja dikirim.
        $data = SimpegJabatanFungsional::create($validator->validated());
        
        return response()->json($data, 201);
    }

    /**
     * Menampilkan satu data spesifik menggunakan Route Model Binding.
     */
    public function show(SimpegJabatanFungsional $jabatanFungsional)
    {
        // Memuat relasi untuk ditampilkan di response
        return response()->json($jabatanFungsional->load(['pangkat']));
    }

    /**
     * Memperbarui data yang ada.
     */
    public function update(Request $request, SimpegJabatanFungsional $jabatanFungsional)
    {
        $validator = Validator::make($request->all(), [
            'pangkat_id' => 'sometimes|required|uuid|exists:simpeg_master_pangkat,id',
            'kode' => ['sometimes', 'required', 'string', 'max:5', Rule::unique('simpeg_jabatan_fungsional')->ignore($jabatanFungsional->id)],
            'nama_jabatan_fungsional' => 'sometimes|required|string|max:30',
            'pangkat' => 'sometimes|required|string|max:10',
            'angka_kredit' => 'sometimes|required|string|max:6',
            'usia_pensiun' => 'sometimes|required|integer',
            'keterangan' => 'nullable|string',
            'tunjangan' => 'nullable|numeric', // Tambahkan validasi tunjangan
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $jabatanFungsional->update($validator->validated());
        return response()->json($jabatanFungsional);
    }

    /**
     * Menghapus data (soft delete).
     */
    public function destroy(SimpegJabatanFungsional $jabatanFungsional)
    {
        // Tambahan: Cek jika jabatan ini sedang digunakan di tabel lain
        // if ($jabatanFungsional->dataJabatanFungsional()->exists()) {
        //     return response()->json(['message' => 'Gagal menghapus: Jabatan ini sedang digunakan.'], 409);
        // }

        $jabatanFungsional->delete();
        return response()->json(['message' => 'Data berhasil dihapus (soft delete).'], 200);
    }

    /**
     * Mengembalikan data dari sampah.
     */
    public function restore($id)
    {
        $data = SimpegJabatanFungsional::onlyTrashed()->find($id);
        if (!$data) {
            return response()->json(['message' => 'Data tidak ditemukan di sampah.'], 404);
        }
        $data->restore();
        return response()->json(['message' => 'Data berhasil dikembalikan.', 'data' => $data]);
    }

    /**
     * Menghapus data secara permanen.
     */
    public function forceDelete($id)
    {
        $data = SimpegJabatanFungsional::onlyTrashed()->find($id);
        if (!$data) {
            return response()->json(['message' => 'Data tidak ditemukan di sampah.'], 404);
        }
        $data->forceDelete();
        return response()->json(null, 204);
    }
}
