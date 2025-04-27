<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MasterOutputPenelitian;
use App\Services\ActivityLogger;

class SimpegOutputPenelitianController extends Controller
{
    public function index(Request $request)
    {
        $outputPenelitian = MasterOutputPenelitian::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $outputPenelitian->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/output-penelitian/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/output-penelitian/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $outputPenelitian
        ]);
    }

    public function show(Request $request, $id)
    {
        $outputPenelitian = MasterOutputPenelitian::find($id);

        if (!$outputPenelitian) {
            return response()->json(['success' => false, 'message' => 'Output Penelitian tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $outputPenelitian,
            'update_url' => url("/api/{$prefix}/output-penelitian/" . $outputPenelitian->id),
            'delete_url' => url("/api/{$prefix}/output-penelitian/" . $outputPenelitian->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode' => 'required|string|max:5',
            'output_penelitian' => 'required|string|max:100',
        ]);

        $outputPenelitian = MasterOutputPenelitian::create([
            'kode' => $request->kode,
            'output_penelitian' => $request->output_penelitian,
        ]);

        ActivityLogger::log('create', $outputPenelitian, $outputPenelitian->toArray());

        return response()->json([
            'success' => true,
            'data' => $outputPenelitian,
            'message' => 'Output Penelitian berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $outputPenelitian = MasterOutputPenelitian::find($id);

        if (!$outputPenelitian) {
            return response()->json(['success' => false, 'message' => 'Output Penelitian tidak ditemukan'], 404);
        }

        $request->validate([
            'kode' => 'required|string|max:5',
            'output_penelitian' => 'required|string|max:100',
        ]);

        $old = $outputPenelitian->getOriginal();

        $outputPenelitian->update([
            'kode' => $request->kode,
            'output_penelitian' => $request->output_penelitian,
        ]);

        $changes = array_diff_assoc($outputPenelitian->toArray(), $old);
        ActivityLogger::log('update', $outputPenelitian, $changes);

        return response()->json([
            'success' => true,
            'data' => $outputPenelitian,
            'message' => 'Output Penelitian berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $outputPenelitian = MasterOutputPenelitian::find($id);
    
        if (!$outputPenelitian) {
            return response()->json(['success' => false, 'message' => 'Output Penelitian tidak ditemukan'], 404);
        }
    
        $outputPenelitianData = $outputPenelitian->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $outputPenelitian->delete(); // Soft delete
    
        ActivityLogger::log('delete', $outputPenelitian, $outputPenelitianData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Output Penelitian berhasil dihapus (soft delete)'
        ]);
    }
}