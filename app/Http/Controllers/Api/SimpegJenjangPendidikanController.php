<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegJenjangPendidikan;
use App\Services\ActivityLogger;

class SimpegJenjangPendidikanController extends Controller
{
    public function index(Request $request)
    {
        $jenjangPendidikan = SimpegJenjangPendidikan::orderBy('urutan_jenjang_pendidikan', 'asc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $jenjangPendidikan->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jenjang-pendidikan/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jenjang-pendidikan/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jenjangPendidikan
        ]);
    }

    public function show(Request $request, $id)
    {
        $jenjangPendidikan = SimpegJenjangPendidikan::find($id);

        if (!$jenjangPendidikan) {
            return response()->json(['success' => false, 'message' => 'Jenjang pendidikan tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jenjangPendidikan,
            'update_url' => url("/api/{$prefix}/jenjang-pendidikan/" . $jenjangPendidikan->id),
            'delete_url' => url("/api/{$prefix}/jenjang-pendidikan/" . $jenjangPendidikan->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'jenjang_singkatan' => 'required|string|max:5',
            'jenjang_pendidikan' => 'required|string|max:30',
            'nama_jenjang_pendidikan_eng' => 'required|string|max:20',
            'urutan_jenjang_pendidikan' => 'required|integer',
            'perguruan_tinggi' => 'required|boolean',
            'pasca_sarjana' => 'required|boolean',
        ]);

        $jenjangPendidikan = SimpegJenjangPendidikan::create([
            'jenjang_singkatan' => $request->jenjang_singkatan,
            'jenjang_pendidikan' => $request->jenjang_pendidikan,
            'nama_jenjang_pendidikan_eng' => $request->nama_jenjang_pendidikan_eng,
            'urutan_jenjang_pendidikan' => $request->urutan_jenjang_pendidikan,
            'perguruan_tinggi' => $request->perguruan_tinggi,
            'pasca_sarjana' => $request->pasca_sarjana,
        ]);

        ActivityLogger::log('create', $jenjangPendidikan, $jenjangPendidikan->toArray());

        return response()->json([
            'success' => true,
            'data' => $jenjangPendidikan,
            'message' => 'Jenjang pendidikan berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $jenjangPendidikan = SimpegJenjangPendidikan::find($id);

        if (!$jenjangPendidikan) {
            return response()->json(['success' => false, 'message' => 'Jenjang pendidikan tidak ditemukan'], 404);
        }

        $request->validate([
            'jenjang_singkatan' => 'required|string|max:5',
            'jenjang_pendidikan' => 'required|string|max:30',
            'nama_jenjang_pendidikan_eng' => 'required|string|max:20',
            'urutan_jenjang_pendidikan' => 'required|integer',
            'perguruan_tinggi' => 'required|boolean',
            'pasca_sarjana' => 'required|boolean',
        ]);

        $old = $jenjangPendidikan->getOriginal();

        $jenjangPendidikan->update([
            'jenjang_singkatan' => $request->jenjang_singkatan,
            'jenjang_pendidikan' => $request->jenjang_pendidikan,
            'nama_jenjang_pendidikan_eng' => $request->nama_jenjang_pendidikan_eng,
            'urutan_jenjang_pendidikan' => $request->urutan_jenjang_pendidikan,
            'perguruan_tinggi' => $request->perguruan_tinggi,
            'pasca_sarjana' => $request->pasca_sarjana,
        ]);

        $changes = array_diff_assoc($jenjangPendidikan->toArray(), $old);
        ActivityLogger::log('update', $jenjangPendidikan, $changes);

        return response()->json([
            'success' => true,
            'data' => $jenjangPendidikan,
            'message' => 'Jenjang pendidikan berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jenjangPendidikan = SimpegJenjangPendidikan::find($id);
    
        if (!$jenjangPendidikan) {
            return response()->json(['success' => false, 'message' => 'Jenjang pendidikan tidak ditemukan'], 404);
        }
    
        $jenjangPendidikanData = $jenjangPendidikan->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $jenjangPendidikan->delete(); // Soft delete
    
        ActivityLogger::log('delete', $jenjangPendidikan, $jenjangPendidikanData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Jenjang pendidikan berhasil dihapus (soft delete)'
        ]);
    }
}