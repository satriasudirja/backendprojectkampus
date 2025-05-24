<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnggotaProfesi;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AnggotaProfesiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $anggotaProfesi = AnggotaProfesi::query();

        // Filter berdasarkan status data (active/trash)
        if ($request->has('status_data')) {
            if ($request->status_data === 'trash') {
                $anggotaProfesi = AnggotaProfesi::onlyTrashed();
            } elseif ($request->status_data === 'all') {
                $anggotaProfesi = AnggotaProfesi::withTrashed();
            }
            // Default: hanya data aktif (tidak perlu kondisi tambahan)
        }

        // Search "Cari Anggota Profesi" - mencari di semua kolom text
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $anggotaProfesi->where(function ($query) use ($searchTerm) {
                $query->where('nama_organisasi', 'like', '%' . $searchTerm . '%')
                      ->orWhere('peran_kedudukan', 'like', '%' . $searchTerm . '%')
                      ->orWhere('waktu_keanggotaan', 'like', '%' . $searchTerm . '%');
            });
        }

        // Filter berdasarkan nama_organisasi
        if ($request->has('nama_organisasi') && !empty($request->nama_organisasi)) {
            $anggotaProfesi->where('nama_organisasi', 'like', '%' . $request->nama_organisasi . '%');
        }

        // Filter berdasarkan peran_kedudukan
        if ($request->has('peran_kedudukan') && !empty($request->peran_kedudukan)) {
            $anggotaProfesi->where('peran_kedudukan', 'like', '%' . $request->peran_kedudukan . '%');
        }

        // Filter berdasarkan waktu_keanggotaan
        if ($request->has('waktu_keanggotaan') && !empty($request->waktu_keanggotaan)) {
            $anggotaProfesi->where('waktu_keanggotaan', 'like', '%' . $request->waktu_keanggotaan . '%');
        }

        // Filter berdasarkan status_pengajuan
        if ($request->has('status_pengajuan') && !empty($request->status_pengajuan)) {
            $anggotaProfesi->where('status_pengajuan', $request->status_pengajuan);
        }

        // Sort by created_at secara default (terbaru)
        $anggotaProfesi->orderBy('created_at', 'desc');

        // Pagination - default 10, bisa diubah dari request
        $perPage = $request->get('per_page', 10);
        $anggotaProfesi = $anggotaProfesi->paginate($perPage);

        // Tambahkan URL untuk update dan delete
        $prefix = $request->segment(2);
        $anggotaProfesi->getCollection()->transform(function ($item, $index) use ($prefix, $request, $anggotaProfesi) {
            // Tambahkan nomor urut
            $item->nomor = ($anggotaProfesi->currentPage() - 1) * $anggotaProfesi->perPage() + $index + 1;
            
            // URL actions
            $item->update_url = url("/api/{$prefix}/anggota-profesi/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/anggota-profesi/" . $item->id);
            
            // Format tanggal sinkron untuk display
            $item->tanggal_sinkron_formatted = $item->tanggal_sinkron ? 
                $item->tanggal_sinkron->format('d/m/Y H:i') : '-';
            
            // Status dalam bahasa Indonesia
            $statusOptions = AnggotaProfesi::getStatusOptions();
            $item->status_pengajuan_text = $statusOptions[$item->status_pengajuan] ?? $item->status_pengajuan;
            
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $anggotaProfesi,
            'meta' => [
                'total' => $anggotaProfesi->total(),
                'per_page' => $anggotaProfesi->perPage(),
                'current_page' => $anggotaProfesi->currentPage(),
                'last_page' => $anggotaProfesi->lastPage(),
            ]
        ]);
    }

    /**
     * Get options for dropdowns
     */
    public function getOptions()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'status_pengajuan' => AnggotaProfesi::getStatusOptions(),
                'status_data' => [
                    'active' => 'Data Aktif',
                    'trash' => 'Data Terhapus',
                    'all' => 'Semua Data'
                ],
                'per_page_options' => [10, 20, 50, 100]
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nama_organisasi' => 'required|string|max:255',
            'peran_kedudukan' => 'required|string|max:255',
            'waktu_keanggotaan' => 'required|string|max:255',
            'status_pengajuan' => ['required', Rule::in(['draft', 'pending', 'approved', 'rejected'])],
        ]);

        DB::beginTransaction();
        try {
            $anggotaProfesi = AnggotaProfesi::create([
                'nama_organisasi' => $request->nama_organisasi,
                'peran_kedudukan' => $request->peran_kedudukan,
                'waktu_keanggotaan' => $request->waktu_keanggotaan,
                'tanggal_sinkron' => now(),
                'status_pengajuan' => $request->status_pengajuan ?? AnggotaProfesi::STATUS_DRAFT,
            ]);

            ActivityLogger::log('create', $anggotaProfesi, $anggotaProfesi->toArray());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $anggotaProfesi,
                'message' => 'Data anggota profesi berhasil ditambahkan'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan anggota profesi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $anggotaProfesi = AnggotaProfesi::find($id);

        if (!$anggotaProfesi) {
            return response()->json(['success' => false, 'message' => 'Data anggota profesi tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        // Format data untuk response
        $anggotaProfesi->tanggal_sinkron_formatted = $anggotaProfesi->tanggal_sinkron ? 
            $anggotaProfesi->tanggal_sinkron->format('d/m/Y H:i') : '-';
        
        $statusOptions = AnggotaProfesi::getStatusOptions();
        $anggotaProfesi->status_pengajuan_text = $statusOptions[$anggotaProfesi->status_pengajuan] ?? $anggotaProfesi->status_pengajuan;

        return response()->json([
            'success' => true,
            'data' => $anggotaProfesi,
            'update_url' => url("/api/{$prefix}/anggota-profesi/" . $anggotaProfesi->id),
            'delete_url' => url("/api/{$prefix}/anggota-profesi/" . $anggotaProfesi->id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $anggotaProfesi = AnggotaProfesi::find($id);

        if (!$anggotaProfesi) {
            return response()->json(['success' => false, 'message' => 'Data anggota profesi tidak ditemukan'], 404);
        }

        $request->validate([
            'nama_organisasi' => 'required|string|max:255',
            'peran_kedudukan' => 'required|string|max:255',
            'waktu_keanggotaan' => 'required|string|max:255',
            'status_pengajuan' => ['required', Rule::in(['draft', 'pending', 'approved', 'rejected'])],
        ]);

        DB::beginTransaction();
        try {
            $old = $anggotaProfesi->getOriginal();

            $anggotaProfesi->update([
                'nama_organisasi' => $request->nama_organisasi,
                'peran_kedudukan' => $request->peran_kedudukan,
                'waktu_keanggotaan' => $request->waktu_keanggotaan,
                'tanggal_sinkron' => now(),
                'status_pengajuan' => $request->status_pengajuan,
            ]);

            $changes = array_diff_assoc($anggotaProfesi->toArray(), $old);
            ActivityLogger::log('update', $anggotaProfesi, $changes);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $anggotaProfesi,
                'message' => 'Data anggota profesi berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui anggota profesi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy($id)
    {
        $anggotaProfesi = AnggotaProfesi::find($id);

        if (!$anggotaProfesi) {
            return response()->json(['success' => false, 'message' => 'Data anggota profesi tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            $anggotaProfesiData = $anggotaProfesi->toArray();
            
            // Soft delete
            $anggotaProfesi->delete();

            ActivityLogger::log('delete', $anggotaProfesi, $anggotaProfesiData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data anggota profesi berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus anggota profesi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan daftar anggota profesi yang sudah dihapus (trash).
     */
    public function trash(Request $request)
    {
        $anggotaProfesi = AnggotaProfesi::onlyTrashed();

        // Filter search
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $anggotaProfesi->where(function ($query) use ($searchTerm) {
                $query->where('nama_organisasi', 'like', '%' . $searchTerm . '%')
                      ->orWhere('peran_kedudukan', 'like', '%' . $searchTerm . '%')
                      ->orWhere('waktu_keanggotaan', 'like', '%' . $searchTerm . '%');
            });
        }

        // Filter berdasarkan nama_organisasi
        if ($request->has('nama_organisasi') && !empty($request->nama_organisasi)) {
            $anggotaProfesi->where('nama_organisasi', 'like', '%' . $request->nama_organisasi . '%');
        }

        // Filter berdasarkan status_pengajuan
        if ($request->has('status_pengajuan') && !empty($request->status_pengajuan)) {
            $anggotaProfesi->where('status_pengajuan', $request->status_pengajuan);
        }

        // Sort by deleted_at secara default (terbaru dihapus)
        $anggotaProfesi->orderBy('deleted_at', 'desc');

        $perPage = $request->get('per_page', 10);
        $anggotaProfesi = $anggotaProfesi->paginate($perPage);

        // Tambahkan URL untuk restore dan force delete
        $prefix = $request->segment(2);
        $anggotaProfesi->getCollection()->transform(function ($item, $index) use ($prefix, $anggotaProfesi) {
            $item->nomor = ($anggotaProfesi->currentPage() - 1) * $anggotaProfesi->perPage() + $index + 1;
            $item->restore_url = url("/api/{$prefix}/anggota-profesi/{$item->id}/restore");
            $item->force_delete_url = url("/api/{$prefix}/anggota-profesi/{$item->id}/force-delete");
            
            // Format dates
            $item->tanggal_sinkron_formatted = $item->tanggal_sinkron ? 
                $item->tanggal_sinkron->format('d/m/Y H:i') : '-';
            $item->deleted_at_formatted = $item->deleted_at->format('d/m/Y H:i');
            
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $anggotaProfesi
        ]);
    }

    /**
     * Memulihkan anggota profesi yang sudah dihapus.
     */
    public function restore($id)
    {
        $anggotaProfesi = AnggotaProfesi::onlyTrashed()->find($id);

        if (!$anggotaProfesi) {
            return response()->json(['success' => false, 'message' => 'Data anggota profesi yang dihapus tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            $anggotaProfesi->restore();
            $anggotaProfesi->update(['tanggal_sinkron' => now()]);

            ActivityLogger::log('restore', $anggotaProfesi, $anggotaProfesi->toArray());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $anggotaProfesi,
                'message' => 'Data anggota profesi berhasil dipulihkan'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memulihkan anggota profesi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menghapus anggota profesi secara permanen dari database.
     */
    public function forceDelete($id)
    {
        $anggotaProfesi = AnggotaProfesi::withTrashed()->find($id);

        if (!$anggotaProfesi) {
            return response()->json(['success' => false, 'message' => 'Data anggota profesi tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            $anggotaProfesiData = $anggotaProfesi->toArray();
            
            // Hapus permanen
            $anggotaProfesi->forceDelete();

            ActivityLogger::log('force_delete', $anggotaProfesi, $anggotaProfesiData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Data anggota profesi berhasil dihapus secara permanen'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus anggota profesi secara permanen: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update status pengajuan anggota profesi.
     */
    public function updateStatus(Request $request, $id)
    {
        $anggotaProfesi = AnggotaProfesi::find($id);

        if (!$anggotaProfesi) {
            return response()->json(['success' => false, 'message' => 'Data anggota profesi tidak ditemukan'], 404);
        }

        $request->validate([
            'status_pengajuan' => ['required', Rule::in(['draft', 'pending', 'approved', 'rejected'])],
        ]);

        DB::beginTransaction();
        try {
            $old = $anggotaProfesi->getOriginal();

            $anggotaProfesi->update([
                'status_pengajuan' => $request->status_pengajuan,
                'tanggal_sinkron' => now(),
            ]);

            $changes = array_diff_assoc($anggotaProfesi->toArray(), $old);
            ActivityLogger::log('update_status', $anggotaProfesi, $changes);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $anggotaProfesi,
                'message' => 'Status anggota profesi berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status anggota profesi: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk operations untuk multiple records
     */
    public function bulkAction(Request $request)
    {
        $request->validate([
            'action' => ['required', Rule::in(['delete', 'restore', 'force_delete', 'update_status'])],
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:anggota_profesi,id',
            'status_pengajuan' => 'required_if:action,update_status|in:draft,pending,approved,rejected'
        ]);

        DB::beginTransaction();
        try {
            $count = 0;
            $action = $request->action;
            
            foreach ($request->ids as $id) {
                switch ($action) {
                    case 'delete':
                        $item = AnggotaProfesi::find($id);
                        if ($item) {
                            $item->delete();
                            $count++;
                        }
                        break;
                        
                    case 'restore':
                        $item = AnggotaProfesi::onlyTrashed()->find($id);
                        if ($item) {
                            $item->restore();
                            $count++;
                        }
                        break;
                        
                    case 'force_delete':
                        $item = AnggotaProfesi::withTrashed()->find($id);
                        if ($item) {
                            $item->forceDelete();
                            $count++;
                        }
                        break;
                        
                    case 'update_status':
                        $item = AnggotaProfesi::find($id);
                        if ($item) {
                            $item->update([
                                'status_pengajuan' => $request->status_pengajuan,
                                'tanggal_sinkron' => now()
                            ]);
                            $count++;
                        }
                        break;
                }
            }

            ActivityLogger::log('bulk_' . $action, null, [
                'ids' => $request->ids,
                'count' => $count,
                'status_pengajuan' => $request->status_pengajuan ?? null
            ]);

            DB::commit();

            $actionText = [
                'delete' => 'dihapus',
                'restore' => 'dipulihkan', 
                'force_delete' => 'dihapus permanen',
                'update_status' => 'diperbarui statusnya'
            ];

            return response()->json([
                'success' => true,
                'message' => "{$count} data anggota profesi berhasil {$actionText[$action]}"
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal melakukan aksi bulk: ' . $e->getMessage()
            ], 500);
        }
    }
}