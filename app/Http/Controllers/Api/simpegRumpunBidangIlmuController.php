<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RumpunBidangIlmu;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class SimpegRumpunBidangIlmuController extends Controller
{
    public function index(Request $request)
    {
        $rumpunBidangIlmu = RumpunBidangIlmu::query();

        // Filter berdasarkan nama bidang
        if ($request->has('nama_bidang')) {
            $rumpunBidangIlmu->where('nama_bidang', 'like', '%' . $request->nama_bidang . '%');
        }

        // Filter berdasarkan kode
        if ($request->has('kode')) {
            $rumpunBidangIlmu->where('kode', 'like', '%' . $request->kode . '%');
        }

        // Filter berdasarkan parent category
        if ($request->has('parent_category')) {
            $rumpunBidangIlmu->where('parent_category', 'like', '%' . $request->parent_category . '%');
        }

        // Filter berdasarkan sub parent category
        if ($request->has('sub_parent_category')) {
            $rumpunBidangIlmu->where('sub_parent_category', 'like', '%' . $request->sub_parent_category . '%');
        }

        $rumpunBidangIlmu = $rumpunBidangIlmu->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $rumpunBidangIlmu->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/rumpun-bidang-ilmu/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/rumpun-bidang-ilmu/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $rumpunBidangIlmu
        ]);
    }

    public function show(Request $request, $id)
    {
        $rumpunBidangIlmu = RumpunBidangIlmu::find($id);

        if (!$rumpunBidangIlmu) {
            return response()->json(['success' => false, 'message' => 'Data rumpun bidang ilmu tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $rumpunBidangIlmu,
            'update_url' => url("/api/{$prefix}/rumpun-bidang-ilmu/" . $rumpunBidangIlmu->id),
            'delete_url' => url("/api/{$prefix}/rumpun-bidang-ilmu/" . $rumpunBidangIlmu->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:5',
            'nama_bidang' => 'required|string|max:100',
            'parent_category' => 'required|string|max:100',
            'sub_parent_category' => 'required|string|max:100',
        ]);

        $rumpunBidangIlmu = RumpunBidangIlmu::create([
            'kode' => $request->kode,
            'nama_bidang' => $request->nama_bidang,
            'parent_category' => $request->parent_category,
            'sub_parent_category' => $request->sub_parent_category,
        ]);

        ActivityLogger::log('create', $rumpunBidangIlmu, $rumpunBidangIlmu->toArray());

        return response()->json([
            'success' => true,
            'data' => $rumpunBidangIlmu,
            'message' => 'Data rumpun bidang ilmu berhasil ditambahkan'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $rumpunBidangIlmu = RumpunBidangIlmu::find($id);

        if (!$rumpunBidangIlmu) {
            return response()->json(['success' => false, 'message' => 'Data rumpun bidang ilmu tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:5',
            'nama_bidang' => 'required|string|max:100',
            'parent_category' => 'required|string|max:100',
            'sub_parent_category' => 'required|string|max:100',
        ]);

        $old = $rumpunBidangIlmu->getOriginal();

        $rumpunBidangIlmu->update([
            'kode' => $request->kode,
            'nama_bidang' => $request->nama_bidang,
            'parent_category' => $request->parent_category,
            'sub_parent_category' => $request->sub_parent_category,
        ]);

        $changes = array_diff_assoc($rumpunBidangIlmu->toArray(), $old);
        ActivityLogger::log('update', $rumpunBidangIlmu, $changes);

        return response()->json([
            'success' => true,
            'data' => $rumpunBidangIlmu,
            'message' => 'Data rumpun bidang ilmu berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $rumpunBidangIlmu = RumpunBidangIlmu::find($id);

        if (!$rumpunBidangIlmu) {
            return response()->json(['success' => false, 'message' => 'Data rumpun bidang ilmu tidak ditemukan'], 404);
        }

        $rumpunBidangIlmuData = $rumpunBidangIlmu->toArray();
        $rumpunBidangIlmu->delete();

        ActivityLogger::log('delete', $rumpunBidangIlmu, $rumpunBidangIlmuData);

        return response()->json([
            'success' => true,
            'message' => 'Data rumpun bidang ilmu berhasil dihapus'
        ]);
    }
}