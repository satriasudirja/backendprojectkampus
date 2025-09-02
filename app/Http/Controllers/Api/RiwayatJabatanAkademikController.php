<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataJabatanAkademik;
use App\Models\SimpegPegawai;
use App\Models\SimpegJabatanAkademik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\ActivityLogger;

class RiwayatJabatanAkademikController extends Controller
{
    // Get all riwayat jabatan akademik (for admin) atau data pegawai sendiri (for dosen)
    public function index(Request $request)
    {
        $perPage = $request->per_page ?? 10;
        $search = $request->search;
        $status = $request->status;
        $prefix = $request->segment(2); // admin, dosen, etc

        $query = SimpegDataJabatanAkademik::with([
            'pegawai',
            'jabatanAkademik'
        ]);

        // Jika bukan admin, batasi hanya data pegawai yang login
        if ($prefix !== 'admin') {
            $user = auth()->user();
            if ($user) {
                $query->where('pegawai_id', $user->id);
            }
        }

        // Filter by search
        if ($search) {
            $query->whereHas('pegawai', function($q) use ($search) {
                $q->where('nip', 'like', '%'.$search.'%')
                  ->orWhere('nama', 'like', '%'.$search.'%');
            })
            ->orWhere('no_sk', 'like', '%'.$search.'%')
            ->orWhere('pejabat_penetap', 'like', '%'.$search.'%');
        }

        // Filter by status pengajuan
        if ($status && $status != 'Semua') {
            $query->where('status_pengajuan', $status);
        }

        $riwayat = $query->orderBy('tmt_jabatan', 'desc')
                        ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $riwayat->map(function ($item) {
                return $this->formatRiwayatJabatanAkademik($item);
            }),
            'pagination' => [
                'current_page' => $riwayat->currentPage(),
                'per_page' => $riwayat->perPage(),
                'total' => $riwayat->total(),
                'last_page' => $riwayat->lastPage()
            ]
        ]);
    }

    // Get riwayat jabatan akademik by pegawai ID (untuk admin)
    public function getByPegawai($pegawaiId)
    {
        $pegawai = SimpegPegawai::find($pegawaiId);

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai tidak ditemukan'
            ], 404);
        }

        $riwayat = $pegawai->dataJabatanAkademik()
            ->with(['jabatanAkademik'])
            ->orderBy('tmt_jabatan', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'pegawai' => [
                'nip' => $pegawai->nip,
                'nama' => $pegawai->nama,
                'jabatan_akademik' => $pegawai->jabatanAkademik->jabatan_akademik ?? '-',
                'unit_kerja' => $pegawai->unitKerja->nama_unit ?? '-',
                'status' => $pegawai->statusAktif->nama_status_aktif ?? '-'
            ],
            'data' => $riwayat->map(function ($item) {
                return $this->formatRiwayatJabatanAkademik($item);
            })
        ]);
    }

    // Get detail riwayat jabatan akademik
    public function show($id)
    {
        $prefix = request()->segment(2);
        
        $query = SimpegDataJabatanAkademik::with([
            'pegawai',
            'jabatanAkademik'
        ]);

        // Jika bukan admin, batasi hanya data pegawai yang login
        if ($prefix !== 'admin') {
            $user = auth()->user();
            if ($user) {
                $query->where('pegawai_id', $user->id);
            }
        }

        $riwayat = $query->find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat jabatan akademik tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatJabatanAkademik($riwayat)
        ]);
    }

    // Store new riwayat jabatan akademik
    public function store(Request $request)
    {
        $prefix = $request->segment(2);
        
        $rules = [
            'jabatan_akademik_id' => 'required|exists:simpeg_jabatan_akademik,id',
            'tmt_jabatan' => 'required|date',
            'no_sk' => 'required|string|max:100',
            'tgl_sk' => 'required|date',
            'pejabat_penetap' => 'required|string|max:100',
            'file_jabatan' => 'nullable|file|mimes:pdf|max:5120',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak'
        ];

        // Untuk admin bisa pilih pegawai, untuk yang lain otomatis pegawai yang login
        if ($prefix === 'admin') {
            $rules['pegawai_id'] = 'required|exists:simpeg_pegawai,id';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except('file_jabatan');

        // Set pegawai_id berdasarkan role
        if ($prefix !== 'admin') {
            $data['pegawai_id'] = auth()->user()->id;
        }

        $data['tgl_input'] = now()->toDateString();

        // Handle file upload
        if ($request->hasFile('file_jabatan')) {
            $file = $request->file('file_jabatan');
            $fileName = 'jabatan_akademik_'.time().'_'.$data['pegawai_id'].'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/jabatan_akademik', $fileName);
            $data['file_jabatan'] = $fileName;
        }

        // Set timestamp berdasarkan status
        switch ($data['status_pengajuan']) {
            case 'diajukan':
                $data['tgl_diajukan'] = now();
                break;
            case 'disetujui':
                $data['tgl_disetujui'] = now();
                break;
            case 'ditolak':
                $data['tgl_ditolak'] = now();
                break;
        }

        $riwayat = SimpegDataJabatanAkademik::create($data);

        ActivityLogger::log('create', $riwayat, $riwayat->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatJabatanAkademik($riwayat),
            'message' => 'Riwayat jabatan akademik berhasil ditambahkan'
        ], 201);
    }

    // Update riwayat jabatan akademik
    public function update(Request $request, $id)
    {
        $prefix = $request->segment(2);
        
        $query = SimpegDataJabatanAkademik::query();

        // Jika bukan admin, batasi hanya data pegawai yang login
        if ($prefix !== 'admin') {
            $user = auth()->user();
            if ($user) {
                $query->where('pegawai_id', $user->id);
            }
        }

        $riwayat = $query->find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat jabatan akademik tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'jabatan_akademik_id' => 'sometimes|exists:simpeg_jabatan_akademik,id',
            'tmt_jabatan' => 'sometimes|date',
            'no_sk' => 'sometimes|string|max:100',
            'tgl_sk' => 'sometimes|date',
            'pejabat_penetap' => 'sometimes|string|max:100',
            'file_jabatan' => 'nullable|file|mimes:pdf|max:5120',
            'status_pengajuan' => 'sometimes|in:draft,diajukan,disetujui,ditolak'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldData = $riwayat->getOriginal();
        $data = $request->except('file_jabatan');

        // Handle file upload
        if ($request->hasFile('file_jabatan')) {
            // Hapus file lama jika ada
            if ($riwayat->file_jabatan) {
                Storage::delete('public/pegawai/jabatan_akademik/'.$riwayat->file_jabatan);
            }

            $file = $request->file('file_jabatan');
            $fileName = 'jabatan_akademik_'.time().'_'.$riwayat->pegawai_id.'.'.$file->getClientOriginalExtension();
            $file->storeAs('public/pegawai/jabatan_akademik', $fileName);
            $data['file_jabatan'] = $fileName;
        }

        // Set timestamp berdasarkan perubahan status
        if ($request->has('status_pengajuan')) {
            switch ($request->status_pengajuan) {
                case 'diajukan':
                    $data['tgl_diajukan'] = now();
                    $data['tgl_disetujui'] = null;
                    $data['tgl_ditolak'] = null;
                    break;
                case 'disetujui':
                    $data['tgl_disetujui'] = now();
                    $data['tgl_ditolak'] = null;
                    break;
                case 'ditolak':
                    $data['tgl_ditolak'] = now();
                    $data['tgl_disetujui'] = null;
                    break;
                case 'draft':
                    $data['tgl_diajukan'] = null;
                    $data['tgl_disetujui'] = null;
                    $data['tgl_ditolak'] = null;
                    break;
            }
        }

        $riwayat->update($data);

        ActivityLogger::log('update', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatRiwayatJabatanAkademik($riwayat),
            'message' => 'Riwayat jabatan akademik berhasil diperbarui'
        ]);
    }

    // Delete riwayat jabatan akademik
    public function destroy($id)
    {
        $prefix = request()->segment(2);
        
        $query = SimpegDataJabatanAkademik::query();

        // Jika bukan admin, batasi hanya data pegawai yang login
        if ($prefix !== 'admin') {
            $user = auth()->user();
            if ($user) {
                $query->where('pegawai_id', $user->id);
            }
        }

        $riwayat = $query->find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat jabatan akademik tidak ditemukan'
            ], 404);
        }

        // Hapus file jika ada
        if ($riwayat->file_jabatan) {
            Storage::delete('public/pegawai/jabatan_akademik/'.$riwayat->file_jabatan);
        }

        $oldData = $riwayat->toArray();
        $riwayat->delete();

        ActivityLogger::log('delete', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Riwayat jabatan akademik berhasil dihapus'
        ]);
    }

    // Batch update status (untuk admin)
    public function batchUpdateStatus(Request $request)
    {
        $prefix = $request->segment(2);
        
        if ($prefix !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = ['status_pengajuan' => $request->status_pengajuan];

        // Set timestamp berdasarkan status
        switch ($request->status_pengajuan) {
            case 'diajukan':
                $updateData['tgl_diajukan'] = now();
                $updateData['tgl_disetujui'] = null;
                $updateData['tgl_ditolak'] = null;
                break;
            case 'disetujui':
                $updateData['tgl_disetujui'] = now();
                $updateData['tgl_ditolak'] = null;
                break;
            case 'ditolak':
                $updateData['tgl_ditolak'] = now();
                $updateData['tgl_disetujui'] = null;
                break;
            case 'draft':
                $updateData['tgl_diajukan'] = null;
                $updateData['tgl_disetujui'] = null;
                $updateData['tgl_ditolak'] = null;
                break;
        }

        SimpegDataJabatanAkademik::whereIn('id', $request->ids)
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui'
        ]);
    }

    // Batch delete (untuk admin)
    public function batchDelete(Request $request)
    {
        $prefix = $request->segment(2);
        
        if ($prefix !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $riwayat = SimpegDataJabatanAkademik::whereIn('id', $request->ids)->get();

        // Hapus file terkait
        foreach ($riwayat as $item) {
            if ($item->file_jabatan) {
                Storage::delete('public/pegawai/jabatan_akademik/'.$item->file_jabatan);
            }
            ActivityLogger::log('delete', $item, $item->toArray());
        }

        SimpegDataJabatanAkademik::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data riwayat jabatan akademik berhasil dihapus'
        ]);
    }

    // Update status pengajuan
    public function updateStatusPengajuan(Request $request, $id)
    {
        $prefix = $request->segment(2);
        
        if ($prefix !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'status_pengajuan' => 'required|in:draft,diajukan,disetujui,ditolak'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $riwayat = SimpegDataJabatanAkademik::find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat jabatan akademik tidak ditemukan'
            ], 404);
        }

        $oldData = $riwayat->getOriginal();
        
        $updateData = ['status_pengajuan' => $request->status_pengajuan];

        // Set timestamp berdasarkan status
        switch ($request->status_pengajuan) {
            case 'diajukan':
                $updateData['tgl_diajukan'] = now();
                break;
            case 'disetujui':
                $updateData['tgl_disetujui'] = now();
                break;
            case 'ditolak':
                $updateData['tgl_ditolak'] = now();
                break;
        }

        $riwayat->update($updateData);

        ActivityLogger::log('update', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui'
        ]);
    }

    // Download file
    public function downloadFile($id)
    {
        $prefix = request()->segment(2);
        
        $query = SimpegDataJabatanAkademik::query();

        // Jika bukan admin, batasi hanya data pegawai yang login
        if ($prefix !== 'admin') {
            $user = auth()->user();
            if ($user) {
                $query->where('pegawai_id', $user->id);
            }
        }

        $riwayat = $query->find($id);

        if (!$riwayat || !$riwayat->file_jabatan) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan'
            ], 404);
        }

        $filePath = storage_path('app/public/pegawai/jabatan_akademik/'.$riwayat->file_jabatan);
        
        if (!file_exists($filePath)) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak ditemukan di storage'
            ], 404);
        }

        return response()->download($filePath);
    }

    // Submit draft untuk dosen
    public function submitDraft($id)
    {
        $prefix = request()->segment(2);
        
        $query = SimpegDataJabatanAkademik::query();

        // Jika bukan admin, batasi hanya data pegawai yang login
       if ($prefix !== 'admin') {
        $user = auth()->user();
        if ($user) {
            // BENAR: Mengambil ID pegawai dari kolom pegawai_id di tabel simpeg_users.
            $query->where('pegawai_id', $user->pegawai_id);
        }
    }

        $riwayat = $query->where('status_pengajuan', 'draft')->find($id);

        if (!$riwayat) {
            return response()->json([
                'success' => false,
                'message' => 'Data riwayat jabatan akademik draft tidak ditemukan atau sudah diajukan'
            ], 404);
        }

        $oldData = $riwayat->getOriginal();
        
        $riwayat->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now()
        ]);

        ActivityLogger::log('update', $riwayat, $oldData);

        return response()->json([
            'success' => true,
            'message' => 'Data jabatan akademik berhasil diajukan untuk persetujuan'
        ]);
    }

    // Format response
    protected function formatRiwayatJabatanAkademik($riwayat)
    {
        return [
            'id' => $riwayat->id,
            'pegawai_id' => $riwayat->pegawai_id,
            'pegawai' => [
                'nip' => $riwayat->pegawai->nip ?? null,
                'nama' => $riwayat->pegawai->nama ?? null
            ],
            'jabatan_akademik_id' => $riwayat->jabatan_akademik_id,
            'jabatan_akademik' => $riwayat->jabatanAkademik ? [
                'id' => $riwayat->jabatanAkademik->id,
                'nama' => $riwayat->jabatanAkademik->jabatan_akademik
            ] : null,
            'tmt_jabatan' => $riwayat->tmt_jabatan,
            'no_sk' => $riwayat->no_sk,
            'tgl_sk' => $riwayat->tgl_sk,
            'pejabat_penetap' => $riwayat->pejabat_penetap,
            'status_pengajuan' => $riwayat->status_pengajuan,
            'dokumen' => $riwayat->file_jabatan ? [
                'nama_file' => $riwayat->file_jabatan,
                'url' => url('storage/pegawai/jabatan_akademik/'.$riwayat->file_jabatan)
            ] : null,
            'timestamps' => [
                'tgl_input' => $riwayat->tgl_input,
                'tgl_diajukan' => $riwayat->tgl_diajukan,
                'tgl_disetujui' => $riwayat->tgl_disetujui,
                'tgl_ditolak' => $riwayat->tgl_ditolak
            ],
            'created_at' => $riwayat->created_at,
            'updated_at' => $riwayat->updated_at
        ];
    }
}