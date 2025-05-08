<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegJabatanStruktural;
use App\Services\ActivityLogger;

class SimpegJabatanStrukturalController extends Controller
{
    public function index(Request $request)
    {
        $jabatan = SimpegJabatanStruktural::with(['unitKerja', 'jenisJabatanStruktural', 'pangkat', 'eselon', 'parent'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Tangkap prefix dari URL
        $prefix = $request->segment(2);

        // Tambahkan link aksi ke setiap item
        $jabatan->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/jabatan-struktural/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/jabatan-struktural/" . $item->id);
            $item->children_count = $item->children()->count();
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $jabatan
        ]);
    }

    public function show(Request $request, $id)
    {
        $jabatan = SimpegJabatanStruktural::with(['unitKerja', 'jenisJabatanStruktural', 'pangkat', 'eselon', 'parent', 'children'])
            ->find($id);

        if (!$jabatan) {
            return response()->json(['success' => false, 'message' => 'Jabatan struktural tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $jabatan,
            'update_url' => url("/api/{$prefix}/jabatan-struktural/" . $jabatan->id),
            'delete_url' => url("/api/{$prefix}/jabatan-struktural/" . $jabatan->id),
            'children_count' => $jabatan->children()->count()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode' => 'required|string|max:5|unique:simpeg_jabatan_struktural',
            'singkatan' => 'required|string|max:50',
            'unit_kerja_id' => 'required|exists:simpeg_unit_kerja,id',
            'jenis_jabatan_struktural_id' => 'required|exists:simpeg_jenis_jabatan_struktural,id',
            'pangkat_id' => 'required|exists:simpeg_master_pangkat,id',
            'eselon_id' => 'required|exists:simpeg_eselon,id',
            'alamat_email' => 'nullable|email|max:100',
            'beban_sks' => 'nullable|integer|min:0',
            'is_pimpinan' => 'required|boolean',
            'aktif' => 'required|boolean',
            'keterangan' => 'nullable|string',
            'parent_jabatan' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($request) {
                    if ($value === $request->kode) {
                        $fail('Jabatan struktural tidak boleh menjadi parent dirinya sendiri');
                    }
                }
            ],
        ]);

        $jabatan = SimpegJabatanStruktural::create($validated);

        ActivityLogger::log('create', $jabatan, $jabatan->toArray());

        return response()->json([
            'success' => true,
            'data' => $jabatan,
            'message' => 'Jabatan Struktural berhasil ditambahkan'
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $jabatan = SimpegJabatanStruktural::find($id);

        if (!$jabatan) {
            return response()->json(['success' => false, 'message' => 'Jabatan struktural tidak ditemukan'], 404);
        }

        $validated = $request->validate([
            'kode' => 'sometimes|required|string|max:5|unique:simpeg_jabatan_struktural,kode,'.$id,
            'singkatan' => 'sometimes|required|string|max:50',
            'unit_kerja_id' => 'sometimes|required|exists:simpeg_unit_kerja,id',
            'jenis_jabatan_struktural_id' => 'sometimes|required|exists:simpeg_jenis_jabatan_struktural,id',
            'pangkat_id' => 'sometimes|required|exists:simpeg_master_pangkat,id',
            'eselon_id' => 'sometimes|required|exists:simpeg_eselon,id',
            'alamat_email' => 'nullable|email|max:100',
            'beban_sks' => 'nullable|integer|min:0',
            'is_pimpinan' => 'sometimes|required|boolean',
            'aktif' => 'sometimes|required|boolean',
            'keterangan' => 'nullable|string',
            'parent_jabatan' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($jabatan, $request) {
                    if ($value === $request->kode || $value === $jabatan->kode) {
                        $fail('Jabatan tidak boleh menjadi parent dirinya sendiri');
                    }
                    // Cek circular reference
                    $current = SimpegJabatanStruktural::where('kode', $value)->first();
                    while ($current) {
                        if ($current->parent_jabatan === $jabatan->kode) {
                            $fail('Terjadi circular reference dalam hierarki jabatan');
                            break;
                        }
                        $current = $current->parent;
                    }
                }
            ],
        ]);

        $oldData = $jabatan->getOriginal();

        $jabatan->update($validated);

        $changes = array_diff_assoc($jabatan->fresh()->toArray(), $oldData);
        ActivityLogger::log('update', $jabatan, $changes);

        return response()->json([
            'success' => true,
            'data' => $jabatan,
            'message' => 'Jabatan Sruktural berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $jabatan = SimpegJabatanStruktural::find($id);

        if (!$jabatan) {
            return response()->json(['success' => false, 'message' => 'Jabatan struktural tidak ditemukan'], 404);
        }

        // Cek apakah jabatan memiliki children
        if ($jabatan->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus jabatan struktural karena memiliki jabatan bawahan'
            ], 422);
        }

        $jabatanData = $jabatan->toArray();
        $jabatan->delete();

        ActivityLogger::log('delete', $jabatan, $jabatanData);

        return response()->json([
            'success' => true,
            'message' => 'Jabatan Struktural berhasil dihapus (soft delete)'
        ]);
    }

    // Endpoint khusus untuk mendapatkan hierarki jabatan
    public function hierarchy(Request $request)
    {
        $rootJabatan = SimpegJabatanStruktural::with('childrenRecursive')
            ->whereNull('parent_jabatan')
            ->get();

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $this->formatHierarchy($rootJabatan, $prefix)
        ]);
    }

    protected function formatHierarchy($jabatans, $prefix)
    {
        return $jabatans->map(function ($jabatan) use ($prefix) {
            $formatted = [
                'id' => $jabatan->id,
                'kode' => $jabatan->kode,
                'nama' => $jabatan->singkatan,
                'update_url' => url("/api/{$prefix}/jabatan-struktural/" . $jabatan->id),
                'delete_url' => url("/api/{$prefix}/jabatan-struktural/" . $jabatan->id),
            ];

            if ($jabatan->relationLoaded('childrenRecursive') && $jabatan->childrenRecursive->isNotEmpty()) {
                $formatted['children'] = $this->formatHierarchy($jabatan->childrenRecursive, $prefix);
            }

            return $formatted;
        });
    }
}