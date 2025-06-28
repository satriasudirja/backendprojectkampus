<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegJenisIzin;
use App\Models\SimpegIzinRecord; // Ditambahkan untuk pengecekan relasi
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SimpegJenisIzinController extends Controller
{
    /**
     * Menampilkan daftar data dengan fitur pencarian/filter.
     */
    public function index(Request $request)
    {
        $query = SimpegJenisIzin::with('jenisKehadiran');

        // Gabungkan fungsionalitas search di sini
        if ($request->filled('search')) {
            $query->where('jenis_izin', 'like', '%' . $request->search . '%')
                  ->orWhere('kode', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('potong_cuti')) {
            $query->where('potong_cuti', $request->potong_cuti);
        }

        $data = $query->orderBy('kode', 'asc')->paginate(10);
        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Menampilkan semua data tanpa paginasi (untuk dropdown).
     */
    public function all()
    {
        $data = SimpegJenisIzin::orderBy('jenis_izin', 'asc')->get();
        return response()->json(['success' => true, 'data' => $data]);
    }
    
    /**
     * Menyimpan data baru.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jenis_kehadiran_id' => 'required|integer|exists:simpeg_jenis_kehadiran,id',
            'kode' => 'required|string|max:5|unique:simpeg_jenis_izin,kode',
            'jenis_izin' => 'required|string|max:50',
            'status_presensi' => 'required|string|max:20',
            'izin_max' => 'required|string|max:3', // Sesuai migration
            'potong_cuti' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $jenisIzin = SimpegJenisIzin::create($validator->validated());
        return response()->json(['success' => true, 'data' => $jenisIzin, 'message' => 'Jenis izin berhasil ditambahkan'], 201);
    }

    /**
     * Menampilkan satu data spesifik.
     */
    public function show($kode)
    {
         $jenisIzin = SimpegJenisIzin::where('kode', $kode)->with('jenisKehadiran')->first();

    if (!$jenisIzin) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }
        return response()->json(['success' => true, 'data' => $jenisIzin]);
    }

    /**
     * Memperbarui data yang ada.
     */
    public function update(Request $request, $kode)
    {
        $jenisIzin = SimpegJenisIzin::where('kode', $kode)->with('jenisKehadiran')->first();

    if (!$jenisIzin) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }

        $validator = Validator::make($request->all(), [
            'jenis_kehadiran_id' => 'sometimes|required|integer|exists:simpeg_jenis_kehadiran,id',
            'jenis_izin' => 'sometimes|required|string|max:50',
            // 'status_presensi' => 'sometimes|required|string|max:20',
            'izin_max' => 'sometimes|required|string|max:3',
            'potong_cuti' => 'sometimes|required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $jenisIzin->update($validator->validated());
        return response()->json(['success' => true, 'data' => $jenisIzin, 'message' => 'Jenis izin berhasil diperbarui']);
    }

    /**
     * Menghapus data (soft delete).
     */
    public function destroy($kode)
    {
        $jenisIzin = SimpegJenisIzin::where('kode', $kode)->first();

    if (!$jenisIzin) {
        return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
    }
        
        // PERBAIKAN: Cek relasi secara langsung ke tabel SimpegIzinRecord
        // Ini untuk menghindari error jika relasi 'izinRecords' tidak ada di model.
        // Asumsi foreign key di tabel simpeg_izin_records adalah 'jenis_izin_id'
        $isUsed = SimpegIzinRecord::where('jenis_izin_id', $jenisIzin->id)->exists();
        
        if ($isUsed) {
            return response()->json(['success' => false, 'message' => 'Gagal menghapus: Jenis Izin ini sedang digunakan.'], 409);
        }

        $jenisIzin->delete();
        return response()->json(['success' => true, 'message' => 'Jenis izin berhasil dihapus']);
    }
}
