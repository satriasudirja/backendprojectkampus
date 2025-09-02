<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegAbsensiRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SimpegKegiatanHarianDosenController extends Controller
{
    /**
     * Menampilkan daftar kegiatan harian untuk dosen yang sedang login.
     */
    public function index(Request $request)
    {
        $pegawai = Auth::user()->pegawai;

        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $search_by = $request->search_by; // Filter berdasarkan kolom spesifik
        $bulan = $request->bulan ?? now()->month;
        $tahun = $request->tahun ?? now()->year;

        $query = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id);

        // Filter Wajib: Bulan dan Tahun
        if ($bulan && $bulan != 'semua') {
            $query->whereMonth('tanggal_absensi', $bulan);
        }
        if ($tahun) {
            $query->whereYear('tanggal_absensi', $tahun);
        }

        // Filter Pencarian
        if ($search && $search_by) {
            switch ($search_by) {
                case 'tgl_kehadiran':
                    $query->whereDate('tanggal_absensi', 'like', '%' . $search . '%');
                    break;
                case 'pekerjaan':
                    $query->where('realisasi_kegiatan', 'like', '%' . $search . '%');
                    break;
                case 'status':
                    $query->where('status_kegiatan', 'like', '%' . $search . '%');
                    break;
                // Tambahkan case lain jika diperlukan
            }
        } elseif ($search) { // Pencarian umum
            $query->where(function ($q) use ($search) {
                $q->where('realisasi_kegiatan', 'like', '%' . $search . '%')
                  ->orWhere('keterangan_kegiatan', 'like', '%' . $search . '%')
                  ->orWhereDate('tanggal_absensi', 'like', '%' . $search . '%');
            });
        }

        $kegiatanHarian = $query->orderBy('tanggal_absensi', 'desc')->paginate($perPage);

        // Transform data untuk response
        $kegiatanHarian->getCollection()->transform(function ($item) {
            return $this->formatKegiatanHarian($item);
        });

        return response()->json([
            'success' => true,
            'data' => $kegiatanHarian,
            'message' => $kegiatanHarian->isEmpty() ? 'Data kegiatan untuk periode yang dipilih tidak ditemukan.' : 'Data berhasil dimuat.'
        ]);
    }

    /**
     * Menampilkan detail satu kegiatan harian.
     */
    public function show($id)
    {
        $pegawai = Auth::user()->pegawai;
        $kegiatan = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)->find($id);

        if (!$kegiatan) {
            return response()->json(['success' => false, 'message' => 'Data kegiatan tidak ditemukan.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatKegiatanHarian($kegiatan)
        ]);
    }

    /**
     * Memperbarui data kegiatan harian (pekerjaan dan file).
     */
    public function update(Request $request, $id)
    {
        $pegawai = Auth::user()->pegawai;
        $kegiatan = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)->find($id);

        if (!$kegiatan) {
            return response()->json(['success' => false, 'message' => 'Data kegiatan tidak ditemukan.'], 404);
        }

        // Hanya bisa diubah jika statusnya 'draft' atau 'ditolak'
        $editableStatuses = ['draft', 'ditolak'];
        if (!in_array($kegiatan->status_kegiatan, $editableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Kegiatan tidak dapat diubah karena sudah diajukan atau divalidasi.'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'pekerjaan' => 'required|string|max:1000',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:2048', // file_kegiatan
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data['realisasi_kegiatan'] = $request->pekerjaan;
        $data['status_kegiatan'] = 'draft'; // Status kembali ke draft setelah diedit

        if ($request->hasFile('file')) {
            // Hapus file lama jika ada
            if ($kegiatan->file_kegiatan) {
                Storage::delete('public/kegiatan_harian/' . $kegiatan->file_kegiatan);
            }
            $file = $request->file('file');
            $fileName = 'kegiatan_' . time() . '_' . $pegawai->id . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/kegiatan_harian', $fileName);
            $data['file_kegiatan'] = $fileName;
        }

        $kegiatan->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Kegiatan harian berhasil diperbarui.',
            'data' => $this->formatKegiatanHarian($kegiatan)
        ]);
    }

    /**
     * Mengajukan kegiatan untuk divalidasi.
     */
    public function submit($id)
    {
        $pegawai = Auth::user()->pegawai;
        $kegiatan = SimpegAbsensiRecord::where('pegawai_id', $pegawai->id)->find($id);

        if (!$kegiatan) {
            return response()->json(['success' => false, 'message' => 'Data kegiatan tidak ditemukan.'], 404);
        }

        if (empty($kegiatan->realisasi_kegiatan)) {
            return response()->json(['success' => false, 'message' => 'Pekerjaan tidak boleh kosong sebelum diajukan.'], 422);
        }

        $submitableStatuses = ['draft', 'ditolak'];
         if (!in_array($kegiatan->status_kegiatan, $submitableStatuses)) {
            return response()->json([
                'success' => false,
                'message' => 'Kegiatan ini sudah diajukan atau divalidasi.'
            ], 422);
        }

        $kegiatan->update(['status_kegiatan' => 'diajukan']);

        return response()->json([
            'success' => true,
            'message' => 'Kegiatan harian berhasil diajukan untuk validasi.'
        ]);
    }

    /**
     * Helper untuk memformat output JSON data kegiatan.
     */
    private function formatKegiatanHarian($item)
    {
        $status = $item->status_kegiatan ?? 'draft';
        $statusInfo = $this->getStatusInfo($status);

        return [
            'id' => $item->id,
            'tgl_kehadiran' => Carbon::parse($item->tanggal_absensi)->isoFormat('dddd, D MMMM YYYY'),
            'jam_masuk' => $item->jam_masuk ? Carbon::parse($item->jam_masuk)->format('H:i') : '-',
            'jam_keluar' => $item->jam_keluar ? Carbon::parse($item->jam_keluar)->format('H:i') : '-',
            'pekerjaan' => $item->realisasi_kegiatan,
            'file' => [
            'nama_file' => $item->file_kegiatan, // Akan menjadi null jika tidak ada file
            'url' => $item->file_kegiatan ? url('storage/kegiatan_harian/' . $item->file_kegiatan) : null,
            'keterangan' => $item->file_kegiatan ? 'File terlampir' : 'Belum ada file di-upload'
        ],
            'status' => $statusInfo,
            'is_valid' => $status === 'disetujui', // Map 'valid?' ke status 'disetujui'
            'can_edit' => in_array($status, ['draft', 'ditolak']),
            'can_submit' => in_array($status, ['draft', 'ditolak']) && !empty($item->realisasi_kegiatan),
        ];
    }

    /**
     * Helper untuk mendapatkan detail status.
     */
    private function getStatusInfo($status)
    {
        $statusMap = [
            'draft' => ['label' => 'Draft', 'color' => 'secondary'],
            'diajukan' => ['label' => 'Diajukan', 'color' => 'info'],
            'disetujui' => ['label' => 'Valid', 'color' => 'success'],
            'ditolak' => ['label' => 'Ditolak', 'color' => 'danger'],
        ];

        return $statusMap[$status] ?? ['label' => ucfirst($status), 'color' => 'dark'];
    }
}