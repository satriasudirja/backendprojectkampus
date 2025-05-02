<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterProdiPerguruanTinggi;
use App\Models\MasterPerguruanTinggi;
use App\Models\SimpegJenjangPendidikan;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SimpegMasterProdiPerguruanTinggiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $prodi = MasterProdiPerguruanTinggi::query();

        // Load relasi
        $prodi->with(['perguruanTinggi', 'jenjangPendidikan']);

        // Filter berdasarkan kode
        if ($request->has('kode')) {
            $prodi->where('kode', 'like', '%' . $request->kode . '%');
        }

        // Filter berdasarkan nama prodi
        if ($request->has('nama_prodi')) {
            $prodi->where('nama_prodi', 'like', '%' . $request->nama_prodi . '%');
        }

        // Filter berdasarkan perguruan tinggi
        if ($request->has('perguruan_tinggi_id')) {
            $prodi->where('perguruan_tinggi_id', $request->perguruan_tinggi_id);
        }

        // Filter berdasarkan jenjang pendidikan
        if ($request->has('jenjang_pendidikan_id')) {
            $prodi->where('jenjang_pendidikan_id', $request->jenjang_pendidikan_id);
        }

        // Filter berdasarkan status aktif
        if ($request->has('is_aktif')) {
            $prodi->where('is_aktif', $request->is_aktif);
        }

        // Filter berdasarkan akreditasi
        if ($request->has('akreditasi')) {
            $prodi->where('akreditasi', $request->akreditasi);
        }

        $prodi = $prodi->paginate(10);

        // Tambahkan URL untuk update dan delete
        $prefix = $request->segment(2);
        $prodi->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/master-prodi-perguruan-tinggi/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/master-prodi-perguruan-tinggi/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $prodi
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'perguruan_tinggi_id' => 'required|exists:simpeg_master_perguruan_tinggi,id',
            'jenjang_pendidikan_id' => 'required|exists:simpeg_jenjang_pendidikan,id',
            'kode' => 'required|string|max:10|unique:simpeg_master_prodi_perguruan_tinggi,kode',
            'nama_prodi' => 'required|string|max:100',
            'alamat' => 'nullable|string',
            'no_telp' => 'nullable|string|max:30',
            'akreditasi' => 'nullable|string|max:5',
            'is_aktif' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $prodi = MasterProdiPerguruanTinggi::create([
                'perguruan_tinggi_id' => $request->perguruan_tinggi_id,
                'jenjang_pendidikan_id' => $request->jenjang_pendidikan_id,
                'kode' => $request->kode,
                'nama_prodi' => $request->nama_prodi,
                'alamat' => $request->alamat,
                'no_telp' => $request->no_telp,
                'akreditasi' => $request->akreditasi,
                'is_aktif' => $request->has('is_aktif') ? $request->is_aktif : true,
            ]);

            // Load relasi untuk response
            $prodi->load(['perguruanTinggi', 'jenjangPendidikan']);

            ActivityLogger::log('create', $prodi, $prodi->toArray());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $prodi,
                'message' => 'Data program studi berhasil ditambahkan'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan program studi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $prodi = MasterProdiPerguruanTinggi::with(['perguruanTinggi', 'jenjangPendidikan'])->find($id);

        if (!$prodi) {
            return response()->json(['success' => false, 'message' => 'Data program studi tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $prodi,
            'update_url' => url("/api/{$prefix}/master-prodi-perguruan-tinggi/" . $prodi->id),
            'delete_url' => url("/api/{$prefix}/master-prodi-perguruan-tinggi/" . $prodi->id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $prodi = MasterProdiPerguruanTinggi::find($id);

        if (!$prodi) {
            return response()->json(['success' => false, 'message' => 'Data program studi tidak ditemukan'], 404);
        }

        $request->validate([
            'perguruan_tinggi_id' => 'required|exists:simpeg_master_perguruan_tinggi,id',
            'jenjang_pendidikan_id' => 'required|exists:simpeg_jenjang_pendidikan,id',
            'kode' => [
                'required',
                'string',
                'max:10',
                Rule::unique('simpeg_master_prodi_perguruan_tinggi')->ignore($prodi->id)
            ],
            'nama_prodi' => 'required|string|max:100',
            'alamat' => 'nullable|string',
            'no_telp' => 'nullable|string|max:30',
            'akreditasi' => 'nullable|string|max:5',
            'is_aktif' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $old = $prodi->getOriginal();

            $prodi->update([
                'perguruan_tinggi_id' => $request->perguruan_tinggi_id,
                'jenjang_pendidikan_id' => $request->jenjang_pendidikan_id,
                'kode' => $request->kode,
                'nama_prodi' => $request->nama_prodi,
                'alamat' => $request->alamat,
                'no_telp' => $request->no_telp,
                'akreditasi' => $request->akreditasi,
                'is_aktif' => $request->has('is_aktif') ? $request->is_aktif : $prodi->is_aktif,
            ]);

            // Load relasi untuk response
            $prodi->load(['perguruanTinggi', 'jenjangPendidikan']);

            $changes = array_diff_assoc($prodi->toArray(), $old);
            ActivityLogger::log('update', $prodi, $changes);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $prodi,
                'message' => 'Data program studi berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui program studi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy($id)
    {
        $prodi = MasterProdiPerguruanTinggi::find($id);

        if (!$prodi) {
            return response()->json(['success' => false, 'message' => 'Data program studi tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            $prodiData = $prodi->toArray();
            
            // Soft delete
            $prodi->delete();

            ActivityLogger::log('delete', $prodi, $prodiData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data program studi berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus program studi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan daftar program studi yang sudah dihapus (trash).
     */
    public function trash(Request $request)
    {
        $prodi = MasterProdiPerguruanTinggi::onlyTrashed();

        // Load relasi
        $prodi->with(['perguruanTinggi', 'jenjangPendidikan']);

        // Filter berdasarkan nama prodi
        if ($request->has('nama_prodi')) {
            $prodi->where('nama_prodi', 'like', '%' . $request->nama_prodi . '%');
        }

        $prodi = $prodi->paginate(10);

        // Tambahkan URL untuk restore dan force delete
        $prefix = $request->segment(2);
        $prodi->getCollection()->transform(function ($item) use ($prefix) {
            $item->restore_url = url("/api/{$prefix}/master-prodi-perguruan-tinggi/{$item->id}/restore");
            $item->force_delete_url = url("/api/{$prefix}/master-prodi-perguruan-tinggi/{$item->id}/force-delete");
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $prodi
        ]);
    }

    /**
     * Memulihkan program studi yang sudah dihapus.
     */
    public function restore($id)
    {
        $prodi = MasterProdiPerguruanTinggi::onlyTrashed()->find($id);

        if (!$prodi) {
            return response()->json(['success' => false, 'message' => 'Data program studi yang dihapus tidak ditemukan'], 404);
        }

        $prodi->restore();

        // Load relasi untuk response
        $prodi->load(['perguruanTinggi', 'jenjangPendidikan']);

        ActivityLogger::log('restore', $prodi, $prodi->toArray());

        return response()->json([
            'success' => true,
            'data' => $prodi,
            'message' => 'Data program studi berhasil dipulihkan'
        ]);
    }

    /**
     * Menghapus program studi secara permanen dari database.
     */
    public function forceDelete($id)
    {
        $prodi = MasterProdiPerguruanTinggi::withTrashed()->find($id);

        if (!$prodi) {
            return response()->json(['success' => false, 'message' => 'Data program studi tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            $prodiData = $prodi->toArray();
            
            // Periksa apakah program studi memiliki relasi dengan tabel lain
            // Jika ada, berikan pesan error bahwa tidak bisa dihapus permanen
            // Contoh: if ($prodi->pendidikanPegawai()->count() > 0) { ... }
            
            // Hapus permanen
            $prodi->forceDelete();

            ActivityLogger::log('force_delete', $prodi, $prodiData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data program studi berhasil dihapus secara permanen'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus program studi secara permanen: ' . $e->getMessage()
            ], 500);
        }
    }
}