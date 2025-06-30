<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegPekerjaan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class SimpegPekerjaanController extends Controller
{
    public function index(Request $request)
    {
        $query = SimpegPekerjaan::query();
        if ($request->filled('search')) {
            $query->where('nama_pekerjaan', 'like', '%' . $request->search . '%')
                  ->orWhere('kode', 'like', '%' . $request->search . '%');
        }
        if ($request->has('trashed')) {
            $query->onlyTrashed();
        }
        $pekerjaan = $query->orderBy('nama_pekerjaan', 'asc')->paginate(10);
        return response()->json($pekerjaan);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode' => 'required|string|max:10|unique:simpeg_pekerjaan,kode',
            'nama_pekerjaan' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pekerjaan = SimpegPekerjaan::create($validator->validated());
        return response()->json($pekerjaan, 201);
    }

    public function show(SimpegPekerjaan $pekerjaan)
    {
        return response()->json($pekerjaan);
    }

    public function update(Request $request, SimpegPekerjaan $pekerjaan)
    {
        $validator = Validator::make($request->all(), [
            'kode' => ['required', 'string', 'max:10', Rule::unique('simpeg_pekerjaan')->ignore($pekerjaan->id)],
            'nama_pekerjaan' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $pekerjaan->update($validator->validated());
        return response()->json($pekerjaan);
    }

    public function destroy(SimpegPekerjaan $pekerjaan)
    {
        // Proteksi agar data master tidak bisa dihapus jika masih digunakan
        if ($pekerjaan->dataKeluarga()->exists() || $pekerjaan->dataRiwayatPekerjaan()->exists()) {
            return response()->json(['message' => 'Gagal menghapus: Pekerjaan ini sedang digunakan di data lain.'], 409);
        }
        $pekerjaan->delete();
        return response()->json(null, 204);
    }
    
    // Anda bisa menambahkan method restore dan forceDelete jika diperlukan
}