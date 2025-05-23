<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenjangPendidikan;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SimpegJenjangPendidikanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $jenjang = JenjangPendidikan::query();

        // Filter berdasarkan jenjang_singkatan
        if ($request->has('jenjang_singkatan')) {
            $jenjang->where('jenjang_singkatan', 'like', '%' . $request->jenjang_singkatan . '%');
        }

        // Filter berdasarkan jenjang_pendidikan
        if ($request->has('jenjang_pendidikan')) {
            $jenjang->where('jenjang_pendidikan', 'like', '%' . $request->jenjang_pendidikan . '%');
        }

        // Filter berdasarkan perguruan_tinggi
        if ($request->has('perguruan_tinggi')) {
            $jenjang->where('perguruan_tinggi', $request->perguruan_tinggi);
        }

        // Filter berdasarkan pasca_sarjana
        if ($request->has('pasca_sarjana')) {
            $jenjang->where('pasca_sarjana', $request->pasca_sarjana);
        }

        // Sort by urutan_jenjang_pendidikan secara default
        $jenjang->orderBy('urutan_jenjang_pendidikan');

        $jenjang = $jenjang->paginate(20); // Tampilkan 20 item per halaman

        // Tambahkan URL untuk update dan delete
        $prefix = $request->segment(2);
        $jenjang->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenjang-pendidikan/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenjang-pendidikan/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenjang
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'jenjang_singkatan' => 'required|string|max:5|unique:simpeg_jenjang_pendidikan,jenjang_singkatan',
            'jenjang_pendidikan' => 'required|string|max:30',
            'nama_jenjang_pendidikan_eng' => 'nullable|string|max:20',
            'urutan_jenjang_pendidikan' => 'required|integer|min:1',
            'perguruan_tinggi' => 'boolean',
            'pasca_sarjana' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $jenjang = JenjangPendidikan::create([
                'jenjang_singkatan' => $request->jenjang_singkatan,
                'jenjang_pendidikan' => $request->jenjang_pendidikan,
                'nama_jenjang_pendidikan_eng' => $request->nama_jenjang_pendidikan_eng,
                'urutan_jenjang_pendidikan' => $request->urutan_jenjang_pendidikan,
                'perguruan_tinggi' => $request->has('perguruan_tinggi') ? $request->perguruan_tinggi : false,
                'pasca_sarjana' => $request->has('pasca_sarjana') ? $request->pasca_sarjana : false,
            ]);

            ActivityLogger::log('create', $jenjang, $jenjang->toArray());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $jenjang,
                'message' => 'Data jenjang pendidikan berhasil ditambahkan'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan jenjang pendidikan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $jenjang = JenjangPendidikan::find($id);

        if (!$jenjang) {
            return response()->json(['success' => false, 'message' => 'Data jenjang pendidikan tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenjang,
            'update_url' => url("/api/{$prefix}/jenjang-pendidikan/" . $jenjang->id),
            'delete_url' => url("/api/{$prefix}/jenjang-pendidikan/" . $jenjang->id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $jenjang = JenjangPendidikan::find($id);

        if (!$jenjang) {
            return response()->json(['success' => false, 'message' => 'Data jenjang pendidikan tidak ditemukan'], 404);
        }

        $request->validate([
            'jenjang_singkatan' => [
                'required',
                'string',
                'max:5',
                Rule::unique('simpeg_jenjang_pendidikan')->ignore($jenjang->id)
            ],
            'jenjang_pendidikan' => 'required|string|max:30',
            'nama_jenjang_pendidikan_eng' => 'nullable|string|max:20',
            'urutan_jenjang_pendidikan' => 'required|integer|min:1',
            'perguruan_tinggi' => 'boolean',
            'pasca_sarjana' => 'boolean',
        ]);

        DB::beginTransaction();
        try {
            $old = $jenjang->getOriginal();

            $jenjang->update([
                'jenjang_singkatan' => $request->jenjang_singkatan,
                'jenjang_pendidikan' => $request->jenjang_pendidikan,
                'nama_jenjang_pendidikan_eng' => $request->nama_jenjang_pendidikan_eng,
                'urutan_jenjang_pendidikan' => $request->urutan_jenjang_pendidikan,
                'perguruan_tinggi' => $request->has('perguruan_tinggi') ? $request->perguruan_tinggi : $jenjang->perguruan_tinggi,
                'pasca_sarjana' => $request->has('pasca_sarjana') ? $request->pasca_sarjana : $jenjang->pasca_sarjana,
            ]);

            $changes = array_diff_assoc($jenjang->toArray(), $old);
            ActivityLogger::log('update', $jenjang, $changes);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $jenjang,
                'message' => 'Data jenjang pendidikan berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui jenjang pendidikan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy($id)
    {
        $jenjang = JenjangPendidikan::find($id);

        if (!$jenjang) {
            return response()->json(['success' => false, 'message' => 'Data jenjang pendidikan tidak ditemukan'], 404);
        }

        // Periksa apakah jenjang pendidikan digunakan di prodi
        $isUsedInProdi = $jenjang->programStudi()->exists();
        if ($isUsedInProdi) {
            return response()->json([
                'success' => false,
                'message' => 'Data jenjang pendidikan tidak dapat dihapus karena masih digunakan di program studi'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $jenjangData = $jenjang->toArray();
            
            // Soft delete
            $jenjang->delete();

            ActivityLogger::log('delete', $jenjang, $jenjangData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data jenjang pendidikan berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jenjang pendidikan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan daftar jenjang pendidikan yang sudah dihapus (trash).
     */
    public function trash(Request $request)
    {
        $jenjang = JenjangPendidikan::onlyTrashed();

        // Filter berdasarkan jenjang_pendidikan
        if ($request->has('jenjang_pendidikan')) {
            $jenjang->where('jenjang_pendidikan', 'like', '%' . $request->jenjang_pendidikan . '%');
        }

        // Sort by urutan_jenjang_pendidikan secara default
        $jenjang->orderBy('urutan_jenjang_pendidikan');

        $jenjang = $jenjang->paginate(10);

        // Tambahkan URL untuk restore dan force delete
        $prefix = $request->segment(2);
        $jenjang->getCollection()->transform(function ($item) use ($prefix) {
            $item->restore_url = url("/api/{$prefix}/jenjang-pendidikan/{$item->id}/restore");
            $item->force_delete_url = url("/api/{$prefix}/jenjang-pendidikan/{$item->id}/force-delete");
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenjang
        ]);
    }

    /**
     * Memulihkan jenjang pendidikan yang sudah dihapus.
     */
    public function restore($id)
    {
        $jenjang = JenjangPendidikan::onlyTrashed()->find($id);

        if (!$jenjang) {
            return response()->json(['success' => false, 'message' => 'Data jenjang pendidikan yang dihapus tidak ditemukan'], 404);
        }

        $jenjang->restore();

        ActivityLogger::log('restore', $jenjang, $jenjang->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenjang,
            'message' => 'Data jenjang pendidikan berhasil dipulihkan'
        ]);
    }

    /**
     * Menghapus jenjang pendidikan secara permanen dari database.
     */
    public function forceDelete($id)
    {
        $jenjang = JenjangPendidikan::withTrashed()->find($id);

        if (!$jenjang) {
            return response()->json(['success' => false, 'message' => 'Data jenjang pendidikan tidak ditemukan'], 404);
        }

        // Periksa apakah jenjang pendidikan digunakan di prodi
        $isUsedInProdi = $jenjang->programStudi()->exists();
        if ($isUsedInProdi) {
            return response()->json([
                'success' => false,
                'message' => 'Data jenjang pendidikan tidak dapat dihapus permanen karena masih digunakan di program studi'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $jenjangData = $jenjang->toArray();
            
            // Hapus permanen
            $jenjang->forceDelete();

            ActivityLogger::log('force_delete', $jenjang, $jenjangData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data jenjang pendidikan berhasil dihapus secara permanen'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus jenjang pendidikan secara permanen: ' . $e->getMessage()
            ], 500);
        }
    }
}