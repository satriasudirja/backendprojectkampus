<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegDataJabatanFungsional;
use App\Models\SimpegJabatanFungsional;
use App\Models\SimpegPegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityLogger;
use Carbon\Carbon;

class SimpegDataJabatanFungsionalController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai untuk pengguna yang login tidak ditemukan.'], 403);
        }
        $pegawai = $user->pegawai;

        // Query dasar
        $query = SimpegDataJabatanFungsional::with(['jabatanFungsional', 'pegawai']);

        // PERBAIKAN: Admin melihat semua data, user lain hanya melihat data miliknya
        if (!$pegawai->is_admin) {
            $query->where('pegawai_id', $pegawai->id);
        }

        // Filtering
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('no_sk', 'like', "%{$search}%")
                  ->orWhereHas('pegawai', function($q) use ($search) {
                      $q->where('nama_lengkap', 'like', "%{$search}%");
                  });
            });
        }
        
        if ($request->filled('status_pengajuan')) {
            $query->where('status_pengajuan', $request->status_pengajuan);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->input('per_page', 10);
        $paginatedData = $query->paginate($perPage);

        // Format setiap item
        $paginatedData->getCollection()->transform(function ($item) {
            return $this->formatDataJabatanFungsional($item);
        });

        return response()->json([
            'success' => true,
            'data' => $paginatedData,
            'message' => 'Data jabatan fungsional berhasil diambil.'
        ]);
    }
    
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jabatan_fungsional_id' => 'required|exists:simpeg_jabatan_fungsional,id',
            'tmt_jabatan' => 'required|date',
            'pejabat_penetap' => 'required|string|max:255',
            'no_sk' => 'required|string|max:255',
            'tanggal_sk' => 'required|date',
            'file_sk_jabatan' => 'required|mimes:pdf,jpg,jpeg,png|max:2048', // max 2MB
            'submit_type' => 'required|in:draft,submit'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        if (!$user || !$user->pegawai) {
            return response()->json(['success' => false, 'message' => 'Data pegawai tidak ditemukan.'], 403);
        }
        $pegawai = $user->pegawai;

        $data = $request->except(['submit_type', 'file_sk_jabatan']);
        $data['pegawai_id'] = $pegawai->id;
        $data['tgl_input'] = now();

        // Handle file upload
        if ($request->hasFile('file_sk_jabatan')) {
            $data['file_sk_jabatan'] = $request->file('file_sk_jabatan')->store('public/dokumen/jabatan_fungsional');
        }

        // Set status
        $submitType = $request->input('submit_type');
        if ($submitType === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data berhasil diajukan untuk persetujuan.';
        } else {
            $data['status_pengajuan'] = 'draft';
            $message = 'Data berhasil disimpan sebagai draft.';
        }

        $dataJabatanFungsional = SimpegDataJabatanFungsional::create($data);
        ActivityLogger::log('create', $dataJabatanFungsional, $dataJabatanFungsional->toArray());

        return response()->json([
            'success' => true,
            'data' => $this->formatDataJabatanFungsional($dataJabatanFungsional->load('jabatanFungsional')),
            'message' => $message
        ], 201);
    }
    
    /**
     * Display the specified resource.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $dataJabatanFungsional = SimpegDataJabatanFungsional::with(['jabatanFungsional', 'pegawai'])->findOrFail($id);
        $user = Auth::user();
        $pegawai = $user ? $user->pegawai : null;

        if (!$pegawai || (!$pegawai->is_admin && $pegawai->id !== $dataJabatanFungsional->pegawai_id)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak diizinkan untuk melihat data ini.'], 403);
        }

        return response()->json(['success' => true, 'data' => $this->formatDataJabatanFungsional($dataJabatanFungsional)]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // FIX: Fetch data first
        $dataJabatanFungsional = SimpegDataJabatanFungsional::findOrFail($id);
        
        // Authorization
        $user = Auth::user();
        $pegawai = $user ? $user->pegawai : null;
        if (!$pegawai || (!$pegawai->is_admin && $pegawai->id !== $dataJabatanFungsional->pegawai_id)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki izin untuk mengubah data ini.'], 403);
        }

        // Prevent editing if already approved, unless by Admin
        if ($dataJabatanFungsional->status_pengajuan === 'disetujui' && !$pegawai->is_admin) {
            return response()->json(['success' => false, 'message' => 'Data yang telah disetujui tidak dapat diubah.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'jabatan_fungsional_id' => 'required|exists:simpeg_master_jabatan_fungsional,id',
            'tmt_jabatan' => 'required|date',
            'pejabat_penetap' => 'required|string|max:255',
            'no_sk' => 'required|string|max:255',
            'tanggal_sk' => 'required|date',
            'file_sk_jabatan' => 'nullable|mimes:pdf,jpg,jpeg,png|max:2048',
            'submit_type' => 'required|in:draft,submit'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }
        
        $oldData = $dataJabatanFungsional->toArray();
        $data = $request->except(['submit_type', 'file_sk_jabatan']);

        // Handle file upload
        if ($request->hasFile('file_sk_jabatan')) {
            // Delete old file
            if ($dataJabatanFungsional->file_sk_jabatan && Storage::exists($dataJabatanFungsional->file_sk_jabatan)) {
                Storage::delete($dataJabatanFungsional->file_sk_jabatan);
            }
            $data['file_sk_jabatan'] = $request->file('file_sk_jabatan')->store('public/dokumen/jabatan_fungsional');
        }

        // Reset status if it was rejected
        if ($dataJabatanFungsional->status_pengajuan === 'ditolak') {
            $data['status_pengajuan'] = 'draft';
            $data['tgl_ditolak'] = null;
            $data['tgl_diajukan'] = null;
        }

        // Handle submit type
        if ($request->submit_type === 'submit') {
            $data['status_pengajuan'] = 'diajukan';
            $data['tgl_diajukan'] = now();
            $message = 'Data berhasil diperbarui dan diajukan.';
        } else {
            // Status remains as it is (draft or whatever it was) unless changed above
            $message = 'Data berhasil diperbarui.';
        }

        $dataJabatanFungsional->update($data);
        ActivityLogger::log('update', $dataJabatanFungsional, $oldData);

        return response()->json([
            'success' => true,
            'data' => $this->formatDataJabatanFungsional($dataJabatanFungsional->load('jabatanFungsional')),
            'message' => $message
        ]);
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $dataJabatanFungsional = SimpegDataJabatanFungsional::findOrFail($id);
        $user = Auth::user();
        $pegawai = $user ? $user->pegawai : null;
        
        if (!$pegawai || (!$pegawai->is_admin && $pegawai->id !== $dataJabatanFungsional->pegawai_id)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki izin untuk menghapus data ini.'], 403);
        }

        // Prevent deletion if already approved, unless by Admin
        if ($dataJabatanFungsional->status_pengajuan === 'disetujui' && !$pegawai->is_admin) {
            return response()->json(['success' => false, 'message' => 'Data yang telah disetujui tidak dapat dihapus.'], 403);
        }

        // Delete file from storage
        if ($dataJabatanFungsional->file_sk_jabatan && Storage::exists($dataJabatanFungsional->file_sk_jabatan)) {
            Storage::delete($dataJabatanFungsional->file_sk_jabatan);
        }
        
        ActivityLogger::log('delete', $dataJabatanFungsional, $dataJabatanFungsional->toArray());
        $dataJabatanFungsional->delete();

        return response()->json(['success' => true, 'message' => 'Data jabatan fungsional berhasil dihapus.']);
    }

    /**
     * Submit a draft for approval.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitDraft($id)
    {
        $dataJabatanFungsional = SimpegDataJabatanFungsional::findOrFail($id);
        $user = Auth::user();
        $pegawai = $user ? $user->pegawai : null;

        if (!$pegawai || $pegawai->id !== $dataJabatanFungsional->pegawai_id) {
            return response()->json(['success' => false, 'message' => 'Anda tidak berwenang melakukan aksi ini.'], 403);
        }
        
        if (!in_array($dataJabatanFungsional->status_pengajuan, ['draft', 'ditolak'])) {
            return response()->json(['success' => false, 'message' => 'Hanya data dengan status draft atau ditolak yang bisa diajukan.'], 422);
        }

        $oldData = $dataJabatanFungsional->toArray();
        $dataJabatanFungsional->update([
            'status_pengajuan' => 'diajukan',
            'tgl_diajukan' => now(),
            'tgl_ditolak' => null, // Reset rejection date
        ]);

        ActivityLogger::log('submit', $dataJabatanFungsional, $oldData);

        return response()->json(['success' => true, 'message' => 'Data berhasil diajukan untuk persetujuan.']);
    }
    
    /**
     * Update status for multiple records. (Admin only)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function batchUpdateStatus(Request $request)
    {
        $user = Auth::user();
        $pegawai = $user ? $user->pegawai : null;

        // This is an admin-only action
        if (!$pegawai || !$pegawai->is_admin) {
            return response()->json(['success' => false, 'message' => 'Anda tidak berwenang melakukan aksi ini.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'exists:simpeg_data_jabatan_fungsional,id',
            'status_pengajuan' => 'required|in:diajukan,disetujui,ditolak,draft'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $updateData = ['status_pengajuan' => $request->status_pengajuan];
        
        switch ($request->status_pengajuan) {
            case 'disetujui':
                $updateData['tgl_disetujui'] = now();
                $updateData['tgl_ditolak'] = null;
                break;
            case 'ditolak':
                $updateData['tgl_ditolak'] = now();
                $updateData['tgl_disetujui'] = null;
                break;
            // Add other cases if needed
        }

        $updatedCount = SimpegDataJabatanFungsional::whereIn('id', $request->ids)->update($updateData);

        return response()->json([
            'success' => true,
            'message' => "Berhasil memperbarui status {$updatedCount} data.",
            'updated_count' => $updatedCount
        ]);
    }
    
    /**
     * Download the specified file.
     *
     * @param  string  $id
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function downloadFile($id)
    {
        $dataJabatanFungsional = SimpegDataJabatanFungsional::findOrFail($id);
        $user = Auth::user();
        $pegawai = $user ? $user->pegawai : null;

        if (!$pegawai || (!$pegawai->is_admin && $pegawai->id !== $dataJabatanFungsional->pegawai_id)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak diizinkan mengunduh file ini.'], 403);
        }

        $filePath = $dataJabatanFungsional->file_sk_jabatan;

        if (!$filePath || !Storage::exists($filePath)) {
            return response()->json(['success' => false, 'message' => 'File tidak ditemukan.'], 404);
        }

        return Storage::download($filePath);
    }
    
    /**
     * Get submission status statistics.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStatusStatistics()
    {
        $user = Auth::user();
        $pegawai = $user ? $user->pegawai : null;
        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pengguna tidak ditemukan.'], 403);
        }

        $query = DB::table('simpeg_data_jabatan_fungsional')->select('status_pengajuan', DB::raw('count(*) as total'));

        if (!$pegawai->is_admin) {
            $query->where('pegawai_id', $pegawai->id);
        }

        $statistics = $query->groupBy('status_pengajuan')->get();
        $formattedStatistics = $statistics->pluck('total', 'status_pengajuan');

        $allStatuses = ['draft', 'diajukan', 'disetujui', 'ditolak'];
        foreach ($allStatuses as $status) {
            if (!isset($formattedStatistics[$status])) {
                $formattedStatistics[$status] = 0;
            }
        }

        return response()->json(['success' => true, 'data' => $formattedStatistics]);
    }

    /**
     * Format the response data.
     *
     * @param  \App\Models\SimpegDataJabatanFungsional  $data
     * @return array
     */
    protected function formatDataJabatanFungsional($data)
    {
        // FIX: Define permission variables
        $user = Auth::user();
        $pegawai = $user ? $user->pegawai : null;
        $isOwner = $pegawai ? $pegawai->id === $data->pegawai_id : false;
        $isAdmin = $pegawai ? $pegawai->is_admin : false;
        
        $status = $data->status_pengajuan;
        $canEdit = ($isOwner && in_array($status, ['draft', 'ditolak'])) || $isAdmin;
        $canSubmit = $isOwner && in_array($status, ['draft', 'ditolak']);
        $canDelete = ($isOwner && in_array($status, ['draft', 'ditolak'])) || ($isAdmin && $status !== 'disetujui');
        
        $statusMapping = [
            'draft' => ['text' => 'Draft', 'color' => 'grey'],
            'diajukan' => ['text' => 'Diajukan', 'color' => 'blue'],
            'disetujui' => ['text' => 'Disetujui', 'color' => 'green'],
            'ditolak' => ['text' => 'Ditolak', 'color' => 'red'],
        ];

        return [
            'id' => $data->id,
            'pegawai_info' => $data->pegawai ? [
                'id' => $data->pegawai->id,
                'nama_lengkap' => $data->pegawai->nama,
            ] : null,
            'jabatan_fungsional_id' => $data->jabatan_fungsional_id,
            'nama_jabatan_fungsional' => $data->jabatanFungsional->nama_jabatan_fungsional ?? '-',
            'tmt_jabatan' => $data->tmt_jabatan,
            'tmt_jabatan_formatted' => $data->tmt_jabatan ? Carbon::parse($data->tmt_jabatan)->format('d-m-Y') : '-',
            'pejabat_penetap' => $data->pejabat_penetap,
            'no_sk' => $data->no_sk,
            'tanggal_sk' => $data->tanggal_sk,
            'tanggal_sk_formatted' => $data->tanggal_sk ? Carbon::parse($data->tanggal_sk)->format('d-m-Y') : '-',
            'file_sk_jabatan' => $data->file_sk_jabatan,
            'file_url' => $data->file_sk_jabatan ? Storage::url($data->file_sk_jabatan) : null,
            'status_pengajuan' => $status,
            'status_info' => $statusMapping[$status] ?? ['text' => ucfirst($status), 'color' => 'grey'],
            'actions' => [
                'can_view' => $isOwner || $isAdmin,
                'can_edit' => $canEdit,
                'can_submit' => $canSubmit,
                'can_delete' => $canDelete,
                'can_download' => $isOwner || $isAdmin,
            ],
            'timestamps' => [
                'tgl_input' => $data->tgl_input ? Carbon::parse($data->tgl_input)->format('d-m-Y H:i') : '-',
                'tgl_diajukan' => $data->tgl_diajukan ? Carbon::parse($data->tgl_diajukan)->format('d-m-Y H:i') : null,
                'tgl_disetujui' => $data->tgl_disetujui ? Carbon::parse($data->tgl_disetujui)->format('d-m-Y H:i') : null,
                'tgl_ditolak' => $data->tgl_ditolak ? Carbon::parse($data->tgl_ditolak)->format('d-m-Y H:i') : null,
            ],
            'created_at' => $data->created_at->format('d-m-Y H:i:s'),
            'updated_at' => $data->updated_at->format('d-m-Y H:i:s'),
        ];
    }
}
