<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HubunganKerja;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\SoftDeletes;

class SimpegHubunganKerjaController extends Controller
{
    public function index(Request $request)
    {
        $hubunganKerja = HubunganKerja::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $hubunganKerja->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/hubungan-kerja/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/hubungan-kerja/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $hubunganKerja
        ]);
    }

    public function show(Request $request, $id)
    {
        $hubunganKerja = HubunganKerja::find($id);

        if (!$hubunganKerja) {
            return response()->json(['success' => false, 'message' => 'Data hubungan kerja tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $hubunganKerja,
            'update_url' => url("/api/{$prefix}/hubungan-kerja/" . $hubunganKerja->id),
            'delete_url' => url("/api/{$prefix}/hubungan-kerja/" . $hubunganKerja->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:2',
            'nama_hub_kerja' => 'required|string|max:30',
            'status_aktif' => 'required|boolean',
            'pns' => 'required|boolean',
        ]);

        $hubunganKerja = HubunganKerja::create([
            'kode' => $request->kode,
            'nama_hub_kerja' => $request->nama_hub_kerja,
            'status_aktif' => $request->status_aktif,
            'pns' => $request->pns,
        ]);

        ActivityLogger::log('create', $hubunganKerja, $hubunganKerja->toArray());

        return response()->json([
            'success' => true,
            'data' => $hubunganKerja,
            'message' => 'Data hubungan kerja berhasil ditambahkan'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $hubunganKerja = HubunganKerja::find($id);

        if (!$hubunganKerja) {
            return response()->json(['success' => false, 'message' => 'Data hubungan kerja tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:2',
            'nama_hub_kerja' => 'required|string|max:30',
            'status_aktif' => 'required|boolean',
            'pns' => 'required|boolean',
        ]);

        $old = $hubunganKerja->getOriginal();

        $hubunganKerja->update([
            'kode' => $request->kode,
            'nama_hub_kerja' => $request->nama_hub_kerja,
            'status_aktif' => $request->status_aktif,
            'pns' => $request->pns,
        ]);

        $changes = array_diff_assoc($hubunganKerja->toArray(), $old);
        ActivityLogger::log('update', $hubunganKerja, $changes);

        return response()->json([
            'success' => true,
            'data' => $hubunganKerja,
            'message' => 'Data hubungan kerja berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $hubunganKerja = HubunganKerja::find($id);
    
        if (!$hubunganKerja) {
            return response()->json(['success' => false, 'message' => 'Data hubungan kerja tidak ditemukan'], 404);
        }
    
        $hubunganKerjaData = $hubunganKerja->toArray();
    
        $hubunganKerja->delete();
    
        ActivityLogger::log('delete', $hubunganKerja, $hubunganKerjaData);
    
        return response()->json([
            'success' => true,
            'message' => 'Data hubungan kerja berhasil dihapus'
        ]);
    }
}