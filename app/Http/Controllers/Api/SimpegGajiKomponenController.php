<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegGajiKomponen;
use App\Services\ActivityLogger;

class SimpegGajiKomponenController extends Controller
{
    public function index(Request $request)
    {
        $gajiKomponen = SimpegGajiKomponen::orderBy('created_at', 'desc')->paginate(10);

        // Tangkap prefix dari URL (contoh: 'admin', 'dosen')
        $prefix = $request->segment(2); // segment(1) = 'api', segment(2) = role prefix

        // Tambahkan link update dan delete ke setiap item
        $gajiKomponen->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-komponen/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-komponen/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $gajiKomponen
        ]);
    }

    public function show(Request $request, $id)
    {
        $gajiKomponen = SimpegGajiKomponen::find($id);

        if (!$gajiKomponen) {
            return response()->json(['success' => false, 'message' => 'Komponen gaji tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $gajiKomponen,
            'update_url' => url("/api/{$prefix}/gaji-komponen/" . $gajiKomponen->id),
            'delete_url' => url("/api/{$prefix}/gaji-komponen/" . $gajiKomponen->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_komponen' => 'required|string|max:20|unique:simpeg_gaji_komponen',
            'nama_komponen' => 'required|string|max:100',
            'jenis' => 'required|string|max:20|in:tunjangan,potongan,benefit',
            'rumus' => 'nullable|string|max:255',
        ]);

        $gajiKomponen = SimpegGajiKomponen::create([
            'kode_komponen' => $request->kode_komponen,
            'nama_komponen' => $request->nama_komponen,
            'jenis' => $request->jenis,
            'rumus' => $request->rumus,
        ]);

        ActivityLogger::log('create', $gajiKomponen, $gajiKomponen->toArray());

        return response()->json([
            'success' => true,
            'data' => $gajiKomponen,
            'message' => 'Komponen gaji berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $gajiKomponen = SimpegGajiKomponen::find($id);

        if (!$gajiKomponen) {
            return response()->json(['success' => false, 'message' => 'Komponen gaji tidak ditemukan'], 404);
        }

        $request->validate([
            'kode_komponen' => 'required|string|max:20|unique:simpeg_gaji_komponen,kode_komponen,' . $id,
            'nama_komponen' => 'required|string|max:100',
            'jenis' => 'required|string|max:20|in:tunjangan,potongan,benefit',
            'rumus' => 'nullable|string|max:255',
        ]);

        $old = $gajiKomponen->getOriginal();

        $gajiKomponen->update([
            'kode_komponen' => $request->kode_komponen,
            'nama_komponen' => $request->nama_komponen,
            'jenis' => $request->jenis,
            'rumus' => $request->rumus,
        ]);

        $changes = array_diff_assoc($gajiKomponen->toArray(), $old);
        ActivityLogger::log('update', $gajiKomponen, $changes);

        return response()->json([
            'success' => true,
            'data' => $gajiKomponen,
            'message' => 'Komponen gaji berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $gajiKomponen = SimpegGajiKomponen::find($id);
    
        if (!$gajiKomponen) {
            return response()->json(['success' => false, 'message' => 'Komponen gaji tidak ditemukan'], 404);
        }
    
        $gajiKomponenData = $gajiKomponen->toArray(); // Simpan dulu isi data sebelum dihapus
    
        $gajiKomponen->delete(); // Soft delete
    
        ActivityLogger::log('delete', $gajiKomponen, $gajiKomponenData); // Log pakai data yang disimpan
    
        return response()->json([
            'success' => true,
            'message' => 'Komponen gaji berhasil dihapus (soft delete)'
        ]);
    }
    
    // Method tambahan untuk mendapatkan komponen berdasarkan jenis
    public function getByJenis(Request $request, $jenis)
    {
        // Validasi jenis
        if (!in_array($jenis, ['tunjangan', 'potongan', 'benefit'])) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis harus berupa "tunjangan", "potongan", atau "benefit"'
            ], 400);
        }
        
        $gajiKomponen = SimpegGajiKomponen::where('jenis', $jenis)
                          ->orderBy('nama_komponen', 'asc')
                          ->paginate(10);

        $prefix = $request->segment(2);

        // Tambahkan link update dan delete ke setiap item
        $gajiKomponen->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-komponen/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-komponen/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $gajiKomponen
        ]);
    }
    
    // Method untuk mencari komponen gaji
    public function search(Request $request)
    {
        $query = SimpegGajiKomponen::query();
        
        // Filter berdasarkan kode komponen
        if ($request->has('kode_komponen')) {
            $query->where('kode_komponen', 'like', '%' . $request->kode_komponen . '%');
        }
        
        // Filter berdasarkan nama komponen
        if ($request->has('nama_komponen')) {
            $query->where('nama_komponen', 'like', '%' . $request->nama_komponen . '%');
        }
        
        // Filter berdasarkan jenis
        if ($request->has('jenis')) {
            $query->where('jenis', $request->jenis);
        }
        
        $gajiKomponen = $query->orderBy('created_at', 'desc')->paginate(10);
        
        $prefix = $request->segment(2);
        
        // Tambahkan link update dan delete ke setiap item
        $gajiKomponen->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-komponen/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-komponen/" . $item->id);
            return $item;
        });
        
        return response()->json([
            'success' => true,
            'data' => $gajiKomponen
        ]);
    }
    
    // Method untuk mendapatkan semua komponen gaji tanpa pagination
    public function all(Request $request)
    {
        $gajiKomponen = SimpegGajiKomponen::orderBy('jenis', 'asc')
                          ->orderBy('nama_komponen', 'asc')
                          ->get();
        
        $prefix = $request->segment(2);
        
        // Tambahkan link update dan delete ke setiap item
        $gajiKomponen->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/gaji-komponen/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/gaji-komponen/" . $item->id);
            return $item;
        });
        
        return response()->json([
            'success' => true,
            'data' => $gajiKomponen
        ]);
    }
}