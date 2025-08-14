<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegBerita;
use App\Models\SimpegJabatanAkademik;
use App\Models\SimpegUnitKerja; // Pastikan ini diimport untuk penggunaan di index filter
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class SimpegBeritaController extends Controller
{
    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $berita = SimpegBerita::query();

        // Filter berdasarkan judul
        if ($request->has('judul')) {
            $berita->where('judul', 'ilike', '%' . $request->judul . '%'); // Using ilike for PostgreSQL
        }

        // Filter berdasarkan unit kerja (jika unit_kerja_id adalah JSONB di database)
        if ($request->has('unit_kerja_id')) {
            $unitKerjaIds = $request->unit_kerja_id;
            if (!is_array($unitKerjaIds)) {
                $unitKerjaIds = [$unitKerjaIds]; // Bungkus dalam array jika single string
            }
            $berita->whereRaw("unit_kerja_id::jsonb @> ?::jsonb", [json_encode($unitKerjaIds)]);
        }

        // Filter berdasarkan tanggal posting
        if ($request->has('tgl_posting_from')) {
            $berita->where('tgl_posting', '>=', $request->tgl_posting_from);
        }
        
        if ($request->has('tgl_posting_to')) {
            $berita->where('tgl_posting', '<=', $request->tgl_posting_to);
        }

        // Filter berdasarkan prioritas
        if ($request->has('prioritas')) {
            // Prioritas harus boolean, pastikan nilai dari request di-cast
            $berita->where('prioritas', (bool)$request->prioritas);
        }

        // Filter berdasarkan jabatan akademik
        // Asumsi relasi many-to-many antara berita dan jabatan akademik melalui tabel pivot
        if ($request->has('jabatan_akademik_id')) {
            $jabatanAkademikIds = $request->jabatan_akademik_id;
            if (!is_array($jabatanAkademikIds)) {
                $jabatanAkademikIds = [$jabatanAkademikIds];
            }
            $berita->whereHas('jabatanAkademik', function($query) use ($jabatanAkademikIds) {
                $query->whereIn('simpeg_jabatan_akademik.id', $jabatanAkademikIds);
            });
        }

        // Load relasi jabatan akademik
        $berita->with('jabatanAkademik');

        $berita = $berita->paginate(10);

        // Tambahkan URL untuk update dan delete
        $prefix = $request->segment(2);
        $berita->getCollection()->transform(function ($item) use ($prefix) {
            $item->update_url = url("/api/{$prefix}/berita/" . $item->id);
            $item->delete_url = url("/api/{$prefix}/berita/" . $item->id);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $berita
        ]);
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'unit_kerja_id' => 'required|array',
            'unit_kerja_id.*' => 'required|uuid', // Tidak validasi ke tabel unit_kerja
            'judul' => 'required|string|max:100',
            'konten' => 'nullable|string',
            'tgl_posting' => 'required|date',
            'tgl_expired' => 'nullable|date|after_or_equal:tgl_posting',
            'prioritas' => 'required|boolean',
            'gambar_berita' => 'nullable|image|max:2048', // Max 2MB
            'file_berita' => 'nullable|file|max:5120', // Max 5MB
            'jabatan_akademik_id' => 'required|array',
            'jabatan_akademik_id.*' => 'required|uuid|exists:simpeg_jabatan_akademik,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        DB::beginTransaction();
        try {
            // Upload gambar berita jika ada
            $gambarBeritaPath = null;
            if ($request->hasFile('gambar_berita')) {
                $gambarBeritaPath = $request->file('gambar_berita')
                    ->store('berita/gambar', 'public');
            }

            // Upload file berita jika ada
            $fileBeritaPath = null;
            if ($request->hasFile('file_berita')) {
                $fileBeritaPath = $request->file('file_berita')
                    ->store('berita/file', 'public');
            }

            // Buat slug dari judul
            $slug = Str::slug($validatedData['judul']);
            $originalSlug = $slug;
            $count = 1;

            // Pastikan slug unik
            while (SimpegBerita::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $count++;
            }

            // Encode unit_kerja_id array to JSON string before saving to database
            $validatedData['unit_kerja_id'] = json_encode($validatedData['unit_kerja_id']);

            // Create berita
            $berita = SimpegBerita::create([
                'unit_kerja_id' => $validatedData['unit_kerja_id'], // Now it's a JSON string
                'judul' => $validatedData['judul'],
                'konten' => $validatedData['konten'],
                'slug' => $slug,
                'tgl_posting' => $validatedData['tgl_posting'],
                'tgl_expired' => $validatedData['tgl_expired'],
                'prioritas' => $validatedData['prioritas'],
                'gambar_berita' => $gambarBeritaPath,
                'file_berita' => $fileBeritaPath,
            ]);

            // Simpan relasi many-to-many dengan jabatan akademik
            $berita->jabatanAkademik()->attach($validatedData['jabatan_akademik_id']);

            // Log aktivitas
            ActivityLogger::log('create', $berita, $berita->toArray());

            DB::commit();

            // Load relasi untuk response
            $berita->load('jabatanAkademik');

            return response()->json([
                'success' => true,
                'data' => $berita,
                'message' => 'Berita berhasil ditambahkan'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            if ($gambarBeritaPath && Storage::disk('public')->exists($gambarBeritaPath)) {
                Storage::disk('public')->delete($gambarBeritaPath);
            }
            
            if ($fileBeritaPath && Storage::disk('public')->exists($fileBeritaPath)) {
                Storage::disk('public')->delete($fileBeritaPath);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan berita: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     * @param Request $request
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function show(Request $request, $id)
    {
        $berita = SimpegBerita::with('jabatanAkademik')->find($id);

        if (!$berita) {
            return response()->json(['success' => false, 'message' => 'Berita tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $berita,
            'update_url' => url("/api/{$prefix}/berita/" . $berita->id),
            'delete_url' => url("/api/{$prefix}/berita/" . $berita->id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function update(Request $request, $id)
    {
        $berita = SimpegBerita::find($id);

        if (!$berita) {
            return response()->json(['success' => false, 'message' => 'Berita tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'unit_kerja_id' => 'required|array',
            'unit_kerja_id.*' => 'required|uuid',
            'judul' => 'required|string|max:100',
            'konten' => 'nullable|string',
            'tgl_posting' => 'required|date',
            'tgl_expired' => 'nullable|date|after_or_equal:tgl_posting',
            'prioritas' => 'required|boolean',
            'gambar_berita' => 'nullable|image|max:2048',
            'file_berita' => 'nullable|file|max:5120',
            'jabatan_akademik_id' => 'required|array',
            'jabatan_akademik_id.*' => 'required|uuid|exists:simpeg_jabatan_akademik,id',
            'gambar_berita_clear' => 'nullable|boolean', // Added for clear flag
            'file_berita_clear' => 'nullable|boolean', // Added for clear flag
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        // Encode unit_kerja_id array to JSON string before saving to database
        if (isset($validatedData['unit_kerja_id'])) {
            $validatedData['unit_kerja_id'] = json_encode($validatedData['unit_kerja_id']);
        }
        
        DB::beginTransaction();
        try {
            $old = $berita->toArray(); // Capture original state as array for comparison

            // Handle gambar_berita update
            if ($request->hasFile('gambar_berita')) {
                if ($berita->gambar_berita && Storage::disk('public')->exists($berita->gambar_berita)) {
                    Storage::disk('public')->delete($berita->gambar_berita);
                }
                $berita->gambar_berita = $request->file('gambar_berita')->store('berita/gambar', 'public');
            } elseif ($request->has('gambar_berita_clear') && (bool)$request->gambar_berita_clear) {
                if ($berita->gambar_berita && Storage::disk('public')->exists($berita->gambar_berita)) {
                    Storage::disk('public')->delete($berita->gambar_berita);
                }
                $berita->gambar_berita = null;
            }

            // Handle file_berita update
            if ($request->hasFile('file_berita')) {
                if ($berita->file_berita && Storage::disk('public')->exists($berita->file_berita)) {
                    Storage::disk('public')->delete($berita->file_berita);
                }
                $berita->file_berita = $request->file('file_berita')->store('berita/file', 'public');
            } elseif ($request->has('file_berita_clear') && (bool)$request->file_berita_clear) {
                if ($berita->file_berita && Storage::disk('public')->exists($berita->file_berita)) {
                    Storage::disk('public')->delete($berita->file_berita);
                }
                $berita->file_berita = null;
            }

            // Update slug if title changed (using validated data)
            if ($berita->judul !== $validatedData['judul']) {
                $slug = Str::slug($validatedData['judul']);
                $originalSlug = $slug;
                $count = 1;
                while (SimpegBerita::where('slug', $slug)->where('id', '!=', $berita->id)->exists()) {
                    $slug = $originalSlug . '-' . $count++;
                }
                $berita->slug = $slug;
            }

            // Fill the model with validated data, excluding file fields and clear flags
            // FIX: Use array_diff_key atau unset untuk menghapus kunci yang tidak perlu dari validatedData
            $dataToFill = $validatedData;
            unset(
                $dataToFill['gambar_berita'],
                $dataToFill['file_berita'],
                $dataToFill['gambar_berita_clear'],
                $dataToFill['file_berita_clear'],
                $dataToFill['jabatan_akademik_id'] // Ini ditangani oleh sync()
            );

            $berita->fill($dataToFill); // Fill data yang sudah bersih
            
            $berita->save(); // Save all changes to the database

            // Sync relation with jabatan akademik
            $berita->jabatanAkademik()->sync($validatedData['jabatan_akademik_id']);
            
            // Log changes
            // FIX: Use array_diff_assoc dengan toArray() untuk perbandingan yang benar
            // atau cukup ambil fresh() jika logging hanya butuh current state setelah update.
            $currentData = $berita->fresh()->toArray(); // Get the fresh state after saving
            $changes = array_diff_assoc($currentData, $old); // Compare old array with new array
            ActivityLogger::log('update', $berita, $changes);

            DB::commit();

            // Load relations for response
            $berita->load('jabatanAkademik');

            return response()->json([
                'success' => true,
                'data' => $berita,
                'message' => 'Berita berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            // Hapus file yang sudah diupload jika ada error (perlu dicek keberadaan path yang baru)
            if (isset($berita->gambar_berita) && Storage::disk('public')->exists($berita->gambar_berita)) {
                Storage::disk('public')->delete($berita->gambar_berita);
            }
            if (isset($berita->file_berita) && Storage::disk('public')->exists($berita->file_berita)) {
                Storage::disk('public')->delete($berita->file_berita);
            }

            \Log::error('Error updating berita: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui berita: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage (soft delete).
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function destroy($id)
    {
        $berita = SimpegBerita::find($id);

        if (!$berita) {
            return response()->json(['success' => false, 'message' => 'Berita tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            $beritaData = $berita->toArray(); // Capture data before soft delete
            
            $berita->delete(); // Soft delete

            ActivityLogger::log('delete', $berita, $beritaData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Berita berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus berita: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan daftar berita yang sudah dihapus (trash).
     * @param Request $request
     * @return \Illuminate->Http->JsonResponse
     */
    public function trash(Request $request)
    {
        $berita = SimpegBerita::onlyTrashed();

        if ($request->has('judul')) {
            $berita->where('judul', 'ilike', '%' . $request->judul . '%');
        }

        $berita->with('jabatanAkademik');

        $berita = $berita->paginate(10);

        $prefix = $request->segment(2);
        $berita->getCollection()->transform(function ($item) use ($prefix) {
            $item->restore_url = url("/api/{$prefix}/berita/{$item->id}/restore");
            $item->force_delete_url = url("/api/{$prefix}/berita/{$item->id}/force-delete");
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => $berita
        ]);
    }

    /**
     * Memulihkan berita yang sudah dihapus.
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function restore($id)
    {
        $berita = SimpegBerita::onlyTrashed()->find($id);

        if (!$berita) {
            return response()->json(['success' => false, 'message' => 'Berita yang dihapus tidak ditemukan'], 404);
        }

        $berita->restore();
        
        $berita->load('jabatanAkademik');

        ActivityLogger::log('restore', $berita, $berita->toArray());

        return response()->json([
            'success' => true,
            'data' => $berita,
            'message' => 'Berita berhasil dipulihkan'
        ]);
    }

    /**
     * Menghapus berita secara permanen dari database.
     * @param int $id
     * @return \Illuminate->Http->JsonResponse
     */
    public function forceDelete($id)
    {
        $berita = SimpegBerita::withTrashed()->find($id);

        if (!$berita) {
            return response()->json(['success' => false, 'message' => 'Berita tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            $beritaData = $berita->toArray();
            
            if ($berita->gambar_berita && Storage::disk('public')->exists($berita->gambar_berita)) {
                Storage::disk('public')->delete($berita->gambar_berita);
            }
            
            if ($berita->file_berita && Storage::disk('public')->exists($berita->file_berita)) {
                Storage::disk('public')->delete($berita->file_berita);
            }
            
            $berita->jabatanAkademik()->detach();
            
            $berita->forceDelete();

            ActivityLogger::log('force_delete', $berita, $beritaData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Berita berhasil dihapus secara permanen'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus berita secara permanen: ' . $e->getMessage()
            ], 500);
        }
    }
}