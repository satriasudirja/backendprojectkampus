<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MasterPerguruanTinggi;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SimpegMasterPerguruanTinggiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perguruanTinggi = MasterPerguruanTinggi::query();

        // Filter berdasarkan kode
        if ($request->has('kode')) {
            $perguruanTinggi->searchByKode($request->kode);
        }

        // Filter berdasarkan nama universitas
        if ($request->has('nama_universitas')) {
            $perguruanTinggi->searchByNama($request->nama_universitas);
        }

        // Filter berdasarkan status aktif
        if ($request->has('is_aktif')) {
            $perguruanTinggi->where('is_aktif', $request->is_aktif);
        }

        // Filter berdasarkan akreditasi
        if ($request->has('akreditasi')) {
            $perguruanTinggi->where('akreditasi', $request->akreditasi);
        }

        $perguruanTinggi = $perguruanTinggi->paginate(10);

        // Tambahkan URL untuk update dan delete
        $prefix = $request->segment(2);
        $perguruanTinggi->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/master-perguruan-tinggi/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/master-perguruan-tinggi/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $perguruanTinggi
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:10|unique:simpeg_master_perguruan_tinggi,kode',
            'nama_universitas' => 'required|string|max:100',
            'alamat' => 'required|string',
            'no_telp' => 'required|string|max:30',
            'email' => 'nullable|email|max:50',
            'website' => 'nullable|url|max:100',
            'akreditasi' => 'nullable|string|max:5',
            'is_aktif' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $perguruanTinggi = MasterPerguruanTinggi::create([
                'kode' => $request->kode,
                'nama_universitas' => $request->nama_universitas,
                'alamat' => $request->alamat,
                'no_telp' => $request->no_telp,
                'email' => $request->email,
                'website' => $request->website,
                'akreditasi' => $request->akreditasi,
                'is_aktif' => $request->has('is_aktif') ? $request->is_aktif : true,
            ]);

            ActivityLogger::log('create', $perguruanTinggi, $perguruanTinggi->toArray());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $perguruanTinggi,
                'message' => 'Data perguruan tinggi berhasil ditambahkan'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan perguruan tinggi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $perguruanTinggi = MasterPerguruanTinggi::find($id);

        if (!$perguruanTinggi) {
            return response()->json(['success' => false, 'message' => 'Data perguruan tinggi tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $perguruanTinggi,
            'update_url' => url("/api/{$prefix}/master-perguruan-tinggi/" . $perguruanTinggi->id),
            'delete_url' => url("/api/{$prefix}/master-perguruan-tinggi/" . $perguruanTinggi->id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $perguruanTinggi = MasterPerguruanTinggi::find($id);

        if (!$perguruanTinggi) {
            return response()->json(['success' => false, 'message' => 'Data perguruan tinggi tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => [
                'required', 
                'string', 
                'max:10', 
                Rule::unique('simpeg_master_perguruan_tinggi')->ignore($perguruanTinggi->id)
            ],
            'nama_universitas' => 'required|string|max:100',
            'alamat' => 'required|string',
            'no_telp' => 'required|string|max:30',
            'email' => 'nullable|email|max:50',
            'website' => 'nullable|url|max:100',
            'akreditasi' => 'nullable|string|max:5',
            'is_aktif' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $old = $perguruanTinggi->getOriginal();

            $perguruanTinggi->update([
                'kode' => $request->kode,
                'nama_universitas' => $request->nama_universitas,
                'alamat' => $request->alamat,
                'no_telp' => $request->no_telp,
                'email' => $request->email,
                'website' => $request->website,
                'akreditasi' => $request->akreditasi,
                'is_aktif' => $request->has('is_aktif') ? $request->is_aktif : $perguruanTinggi->is_aktif,
            ]);

            $changes = array_diff_assoc($perguruanTinggi->toArray(), $old);
            ActivityLogger::log('update', $perguruanTinggi, $changes);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $perguruanTinggi,
                'message' => 'Data perguruan tinggi berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui perguruan tinggi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy($id)
    {
        $perguruanTinggi = MasterPerguruanTinggi::find($id);

        if (!$perguruanTinggi) {
            return response()->json(['success' => false, 'message' => 'Data perguruan tinggi tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            $perguruanTinggiData = $perguruanTinggi->toArray();
            
            // Soft delete
            $perguruanTinggi->delete();

            ActivityLogger::log('delete', $perguruanTinggi, $perguruanTinggiData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data perguruan tinggi berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus perguruan tinggi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan daftar perguruan tinggi yang sudah dihapus (trash).
     */
    public function trash(Request $request)
    {
        $perguruanTinggi = MasterPerguruanTinggi::onlyTrashed();

        // Filter berdasarkan nama universitas
        if ($request->has('nama_universitas')) {
            $perguruanTinggi->searchByNama($request->nama_universitas);
        }

        $perguruanTinggi = $perguruanTinggi->paginate(10);

        // Tambahkan URL untuk restore dan force delete
        $prefix = $request->segment(2);
        $perguruanTinggi->getCollection()->transform(function ($item) use ($prefix) {
            $item->restore_url = url("/api/{$prefix}/master-perguruan-tinggi/{$item->id}/restore");
            $item->force_delete_url = url("/api/{$prefix}/master-perguruan-tinggi/{$item->id}/force-delete");
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $perguruanTinggi
        ]);
    }

    /**
     * Memulihkan perguruan tinggi yang sudah dihapus.
     */
    public function restore($id)
    {
        $perguruanTinggi = MasterPerguruanTinggi::onlyTrashed()->find($id);

        if (!$perguruanTinggi) {
            return response()->json(['success' => false, 'message' => 'Data perguruan tinggi yang dihapus tidak ditemukan'], 404);
        }

        $perguruanTinggi->restore();

        ActivityLogger::log('restore', $perguruanTinggi, $perguruanTinggi->toArray());

        return response()->json([
            'success' => true,
            'data' => $perguruanTinggi,
            'message' => 'Data perguruan tinggi berhasil dipulihkan'
        ]);
    }

    /**
     * Menghapus perguruan tinggi secara permanen dari database.
     */
    public function forceDelete($id)
    {
        $perguruanTinggi = MasterPerguruanTinggi::withTrashed()->find($id);

        if (!$perguruanTinggi) {
            return response()->json(['success' => false, 'message' => 'Data perguruan tinggi tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            $perguruanTinggiData = $perguruanTinggi->toArray();
            
            // Periksa apakah perguruan tinggi memiliki relasi dengan tabel lain
            // Jika ada, berikan pesan error bahwa tidak bisa dihapus permanen
            // Contoh: if ($perguruanTinggi->pendidikanPegawai()->count() > 0) { ... }
            
            // Hapus permanen
            $perguruanTinggi->forceDelete();

            ActivityLogger::log('force_delete', $perguruanTinggi, $perguruanTinggiData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data perguruan tinggi berhasil dihapus secara permanen'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus perguruan tinggi secara permanen: ' . $e->getMessage()
            ], 500);
        }
    }
}