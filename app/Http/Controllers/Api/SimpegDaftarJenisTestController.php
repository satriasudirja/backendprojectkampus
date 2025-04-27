<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegDaftarJenisTest;
use App\Services\ActivityLogger;

class SimpegDaftarJenisTestController extends Controller
{
    public function index(Request $request)
    {
        // Include data yang terhapus juga
        $jenisTest = SimpegDaftarJenisTest::withTrashed()
                        ->orderBy('created_at', 'desc')
                        ->paginate(10);

        $prefix = $request->segment(2);

        $jenisTest->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenis-test/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenis-test/" . $item->id);
            $item->restore_url = $item->trashed() ? url("/api/{$prefix}/jenis-test/" . $item->id . "/restore") : null;
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenisTest
        ]);
    }

    public function show(Request $request, $id)
    {
        $jenisTest = SimpegDaftarJenisTest::withTrashed()->find($id);

        if (!$jenisTest) {
            return response()->json(['success' => false, 'message' => 'Jenis Test tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenisTest,
            'update_url' => url("/api/{$prefix}/jenis-test/" . $jenisTest->id),
            'delete_url' => url("/api/{$prefix}/jenis-test/" . $jenisTest->id),
            'restore_url' => $jenisTest->trashed() ? url("/api/{$prefix}/jenis-test/" . $jenisTest->id . "/restore") : null,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:4|unique:simpeg_daftar_jenis_test,kode', // Sesuaikan dengan panjang kode
            'jenis_tes' => 'required|string|max:25',
            'nilai_minimal' => 'nullable|numeric', // Validasi numeric, bisa null
            'nilai_maksimal' => 'nullable|numeric', // Validasi numeric, bisa null
        ]);

        $jenisTest = SimpegDaftarJenisTest::create([
            'kode' => $request->kode,
            'jenis_tes' => $request->jenis_tes,
            'nilai_minimal' => $request->nilai_minimal, // Bisa null
            'nilai_maksimal' => $request->nilai_maksimal, // Bisa null
        ]);

        ActivityLogger::log('create', $jenisTest, $jenisTest->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisTest,
            'message' => 'Jenis Test berhasil ditambahkan'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $jenisTest = SimpegDaftarJenisTest::withTrashed()->find($id);

        if (!$jenisTest) {
            return response()->json(['success' => false, 'message' => 'Jenis Test tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:4|unique:simpeg_daftar_jenis_test,kode,' . $id, // Sesuaikan dengan panjang kode
            'jenis_tes' => 'required|string|max:25',
            'nilai_minimal' => 'nullable|numeric',
            'nilai_maksimal' => 'nullable|numeric',
        ]);

        $old = $jenisTest->getOriginal();

        $jenisTest->update([
            'kode' => $request->kode,
            'jenis_tes' => $request->jenis_tes,
            'nilai_minimal' => $request->nilai_minimal,
            'nilai_maksimal' => $request->nilai_maksimal,
        ]);

        $changes = array_diff_assoc($jenisTest->toArray(), $old);
        ActivityLogger::log('update', $jenisTest, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenisTest,
            'message' => 'Jenis Test berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jenisTest = SimpegDaftarJenisTest::find($id);

        if (!$jenisTest) {
            return response()->json(['success' => false, 'message' => 'Jenis Test tidak ditemukan'], 404);
        }

        $jenisTestData = $jenisTest->toArray();

        $jenisTest->delete(); // Soft delete

        ActivityLogger::log('delete', $jenisTest, $jenisTestData);

        return response()->json([
            'success' => true,
            'message' => 'Jenis Test berhasil dihapus (soft delete)'
        ]);
    }

    public function restore($id)
    {
        $jenisTest = SimpegDaftarJenisTest::withTrashed()->find($id);

        if (!$jenisTest) {
            return response()->json(['success' => false, 'message' => 'Jenis Test tidak ditemukan'], 404);
        }

        if (!$jenisTest->trashed()) {
            return response()->json(['success' => false, 'message' => 'Jenis Test tidak dalam status terhapus'], 400);
        }

        $jenisTest->restore();

        ActivityLogger::log('restore', $jenisTest, $jenisTest->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenisTest,
            'message' => 'Jenis Test berhasil dipulihkan'
        ]);
    }

    public function forceDelete($id)
    {
        $jenisTest = SimpegDaftarJenisTest::withTrashed()->find($id);

        if (!$jenisTest) {
            return response()->json(['success' => false, 'message' => 'Jenis Test tidak ditemukan'], 404);
        }

        $jenisTestData = $jenisTest->toArray();

        $jenisTest->forceDelete();

        ActivityLogger::log('force_delete', $jenisTest, $jenisTestData);

        return response()->json([
            'success' => true,
            'message' => 'Jenis Test berhasil dihapus permanen'
        ]);
    }
}
