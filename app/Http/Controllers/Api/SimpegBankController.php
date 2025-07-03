<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegBank;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class SimpegBankController extends Controller
{
    public function index(Request $request)
    {
        $query = SimpegBank::query();
        if ($request->filled('search')) {
            $query->where('nama_bank', 'like', '%' . $request->search . '%')
                  ->orWhere('kode', 'like', '%' . $request->search . '%');
        }
        if ($request->has('trashed')) {
            $query->onlyTrashed();
        }
        $banks = $query->orderBy('nama_bank', 'asc')->paginate(10);
        return response()->json($banks);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode' => 'required|string|max:10|unique:simpeg_bank,kode',
            'nama_bank' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bank = SimpegBank::create($validator->validated());
        return response()->json($bank, 201);
    }

    public function show(SimpegBank $bank)
    {
        return response()->json($bank);
    }

    public function update(Request $request, SimpegBank $bank)
    {
        $validator = Validator::make($request->all(), [
            'kode' => ['required', 'string', 'max:10', Rule::unique('simpeg_bank')->ignore($bank->id)],
            'nama_bank' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $bank->update($validator->validated());
        return response()->json($bank);
    }

    public function destroy($id)
{
    $bank = SimpegBank::find($id);

    if (!$bank) {
        return response()->json([
            'message' => 'Data tidak ditemukan.'
        ], 404);
    }

    if ($bank->pegawai()->exists()) {
        return response()->json([
            'message' => 'Gagal menghapus: Bank ini sedang digunakan oleh pegawai.'
        ], 409);
    }

    $bank->delete();

    return response()->json([
        'message' => 'Bank berhasil dihapus (soft delete).',
        'data' => $bank
    ], 200);
}


    public function restore($id)
    {
        $bank = SimpegBank::onlyTrashed()->find($id);
        if (!$bank) {
            return response()->json(['message' => 'Data tidak ditemukan di sampah.'], 404);
        }
        $bank->restore();
        return response()->json(['message' => 'Data berhasil dikembalikan.', 'data' => $bank]);
    }

    public function forceDelete($id)
    {
        $bank = SimpegBank::onlyTrashed()->find($id);
        if (!$bank) {
            return response()->json(['message' => 'Data tidak ditemukan di sampah.'], 404);
        }
        $bank->forceDelete();
        return response()->json(null, 204);
    }
}