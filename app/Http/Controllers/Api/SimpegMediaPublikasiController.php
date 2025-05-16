<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegMediaPublikasi;
use App\Services\ActivityLogger;

class SimpegMediaPublikasiController extends Controller
{
    public function index(Request $request)
    {
        $mediaPublikasi = SimpegMediaPublikasi::orderBy('created_at', 'desc')->paginate(10);

        $prefix = $request->segment(2);

        $mediaPublikasi->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/media-publikasi/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/media-publikasi/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $mediaPublikasi,
        ]);
    }

    public function show(Request $request, $id)
    {
        $mediaPublikasi = SimpegMediaPublikasi::find($id);

        if (!$mediaPublikasi) {
            return response()->json(['success' => false, 'message' => 'Media publikasi tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $mediaPublikasi,
            'update_url' => url("/api/{$prefix}/media-publikasi/" . $mediaPublikasi->id),
            'delete_url' => url("/api/{$prefix}/media-publikasi/" . $mediaPublikasi->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama' => 'required|string|max:255|unique:simpeg_media_publikasi,nama',
        ]);

        $mediaPublikasi = SimpegMediaPublikasi::create([
            'nama' => $request->nama,
        ]);

        ActivityLogger::log('create', $mediaPublikasi, $mediaPublikasi->toArray());

        return response()->json([
            'success' => true,
            'data' => $mediaPublikasi,
            'message' => 'Media publikasi berhasil ditambahkan',
        ]);
    }

    public function update(Request $request, $id)
    {
        $mediaPublikasi = SimpegMediaPublikasi::find($id);

        if (!$mediaPublikasi) {
            return response()->json(['success' => false, 'message' => 'Media publikasi tidak ditemukan'], 404);
        }

        $request->validate([
            'nama' => 'required|string|max:255|unique:simpeg_media_publikasi,nama,' . $mediaPublikasi->id,
        ]);

        $old = $mediaPublikasi->getOriginal();

        $mediaPublikasi->update([
            'nama' => $request->nama,
        ]);

        $changes = array_diff_assoc($mediaPublikasi->toArray(), $old);
        ActivityLogger::log('update', $mediaPublikasi, $changes);

        return response()->json([
            'success' => true,
            'data' => $mediaPublikasi,
            'message' => 'Media publikasi berhasil diperbarui',
        ]);
    }

    public function destroy($id)
    {
        $mediaPublikasi = SimpegMediaPublikasi::find($id);

        if (!$mediaPublikasi) {
            return response()->json(['success' => false, 'message' => 'Media publikasi tidak ditemukan'], 404);
        }

        $dataBeforeDelete = $mediaPublikasi->toArray();

        $mediaPublikasi->delete();

        ActivityLogger::log('delete', $mediaPublikasi, $dataBeforeDelete);

        return response()->json([
            'success' => true,
            'message' => 'Media publikasi berhasil dihapus (soft delete)',
        ]);
    }

    // Semua data tanpa pagination (misal untuk dropdown)
    public function all(Request $request)
    {
        $mediaPublikasi = SimpegMediaPublikasi::orderBy('nama', 'asc')->get();

        $prefix = $request->segment(2);

        $mediaPublikasi->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/media-publikasi/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/media-publikasi/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $mediaPublikasi,
        ]);
    }

    // Method pencarian sederhana
    public function search(Request $request)
    {
        $query = SimpegMediaPublikasi::query();

        if ($request->has('nama')) {
            $query->where('nama', 'like', '%' . $request->nama . '%');
        }

        $mediaPublikasi = $query->orderBy('nama', 'asc')->paginate(10);

        $prefix = $request->segment(2);

        $mediaPublikasi->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/media-publikasi/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/media-publikasi/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $mediaPublikasi,
        ]);
    }
}
