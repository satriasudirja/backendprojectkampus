<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegUnitKerja;
use App\Services\ActivityLogger;

class SimpegUnitKerjaController extends Controller
{
    public function index(Request $request)
    {
        $units = SimpegUnitKerja::orderBy('created_at', 'desc')->paginate(10);

        $prefix = $request->segment(2);

        $units->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/unit-kerja/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/unit-kerja/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $units
        ]);
    }

    public function show(Request $request, $id)
    {
        $unit = SimpegUnitKerja::find($id);

        if (!$unit) {
            return response()->json(['success' => false, 'message' => 'Unit tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $unit,
            'update_url' => url("/api/{$prefix}/unit-kerja/" . $unit->id),
            'delete_url' => url("/api/{$prefix}/unit-kerja/" . $unit->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'kode_unit' => 'required|unique:simpeg_unit_kerja,kode_unit',
            'nama_unit' => 'required|string',
            'parent_unit_id' => 'nullable|string',
        ]);

        $unit = SimpegUnitKerja::create($request->all());

        ActivityLogger::log('create', $unit, $unit->toArray());

        return response()->json([
            'success' => true,
            'data' => $unit,
            'message' => 'Unit kerja berhasil ditambahkan'
        ]);
    }

    public function update(Request $request, $id)
    {
        $unit = SimpegUnitKerja::find($id);

        if (!$unit) {
            return response()->json(['success' => false, 'message' => 'Unit tidak ditemukan'], 404);
        }

        $request->validate([
            'kode_unit' => 'required|string',
            'nama_unit' => 'required|string',
        ]);

        $old = $unit->getOriginal();

        $unit->update($request->all());

        $changes = array_diff_assoc($unit->toArray(), $old);
        ActivityLogger::log('update', $unit, $changes);

        return response()->json([
            'success' => true,
            'data' => $unit,
            'message' => 'Unit kerja berhasil diperbarui'
        ]);
    }

    public function destroy($id)
    {
        $unit = SimpegUnitKerja::find($id);

        if (!$unit) {
            return response()->json(['success' => false, 'message' => 'Unit tidak ditemukan'], 404);
        }

        $unitData = $unit->toArray();

        $unit->delete();

        ActivityLogger::log('delete', $unit, $unitData);

        return response()->json([
            'success' => true,
            'message' => 'Unit kerja berhasil dihapus (soft delete)'
        ]);
    }
    public function dropdown()
    {
        $unitKerja = SimpegUnitKerja::select('kode_unit as id', 'nama_unit as text')
            ->orderBy('nama_unit', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $unitKerja
        ]);
    }

    // Show method (modified to handle numeric vs UUID IDs)
//     public function show($id)
//     {
//         // Check if the ID is "dropdown" and handle it specially
//         if ($id === 'dropdown') {
//             return $this->dropdown();
//         }

//         // Regular show logic
//         $unitKerja = SimpegUnitKerja::find($id);

//         if (!$unitKerja) {
//             return response()->json(['success' => false, 'message' => 'Unit kerja tidak ditemukan'], 404);
//         }

//         return response()->json([
//             'success' => true,
//             'data' => $unitKerja
//         ]);
//     }
}
