<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SimpegBerita;
use App\Models\SimpegJabatanAkademik;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator; // <-- PASTIKAN BARIS INI ADA UNTUK KONSISTENSI

class SimpegBeritaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $berita = SimpegBerita::query();

        // Filter berdasarkan judul
        if ($request->has('judul')) {
            $berita->where('judul', 'like', '%' . $request->judul . '%');
        }

        // Filter berdasarkan unit kerja (jika unit_kerja_id adalah JSON)
        if ($request->has('unit_kerja_id')) {
            $berita->whereRaw("unit_kerja_id::jsonb @> ?::jsonb", [json_encode([$request->unit_kerja_id])]);
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
            $berita->where('prioritas', $request->prioritas);
        }

        // Filter berdasarkan jabatan akademik
        if ($request->has('jabatan_akademik_id')) {
            $berita->whereHas('jabatanAkademik', function($query) use ($request) {
                $query->where('jabatan_akademik_id', $request->jabatan_akademik_id);
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
     */
    public function store(Request $request)
    {
        $request->validate([
            'unit_kerja_id' => 'required|array',
            'unit_kerja_id.*' => 'required|integer', // Tidak validasi ke tabel unit_kerja
            'judul' => 'required|string|max:100',
            'konten' => 'nullable|string',
            'tgl_posting' => 'required|date',
            'tgl_expired' => 'nullable|date|after_or_equal:tgl_posting',
            'prioritas' => 'required|boolean',
            'gambar_berita' => 'nullable|image|max:2048', // Max 2MB
            'file_berita' => 'nullable|file|max:5120', // Max 5MB
            'jabatan_akademik_id' => 'required|array',
            'jabatan_akademik_id.*' => 'required|integer|exists:simpeg_jabatan_akademik,id',
        ]);

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
            $slug = Str::slug($request->judul);
            $originalSlug = $slug;
            $count = 1;

            // Pastikan slug unik
            while (SimpegBerita::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $count++;
            }

            // Buat berita
            $berita = SimpegBerita::create([
                'unit_kerja_id' => $request->unit_kerja_id,
                'judul' => $request->judul,
                'konten' => $request->konten,
                'slug' => $slug,
                'tgl_posting' => $request->tgl_posting,
                'tgl_expired' => $request->tgl_expired,
                'prioritas' => $request->prioritas,
                'gambar_berita' => $gambarBeritaPath,
                'file_berita' => $fileBeritaPath,
            ]);

            // Simpan relasi dengan jabatan akademik
            $berita->jabatanAkademik()->attach($request->jabatan_akademik_id);

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
            
            // Hapus file yang sudah diupload jika ada error
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
     */
 public function update(Request $request, $id)
    {
        $berita = SimpegBerita::find($id);

        if (!$berita) {
            return response()->json(['success' => false, 'message' => 'Berita tidak ditemukan'], 404);
        }

        $validator = Validator::make($request->all(), [
            'unit_kerja_id' => 'required|array',
            'unit_kerja_id.*' => 'required|integer', // Tidak validasi ke tabel unit_kerja
            'judul' => 'required|string|max:100',
            'konten' => 'nullable|string',
            'tgl_posting' => 'required|date',
            'tgl_expired' => 'nullable|date|after_or_equal:tgl_posting',
            'prioritas' => 'required|boolean',
            'gambar_berita' => 'nullable|image|max:2048', // Max 2MB
            'file_berita' => 'nullable|file|max:5120', // Max 5MB
            'jabatan_akademik_id' => 'required|array',
            'jabatan_akademik_id.*' => 'required|integer|exists:simpeg_jabatan_akademik,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $validatedData = $validator->validated();

        // --- MULAI PERUBAHAN UTAMA DI SINI ---
        // Lakukan JSON encode untuk field array sebelum disimpan ke DB (karena DB adalah TEXT)
        if (isset($validatedData['unit_kerja_id']) && is_array($validatedData['unit_kerja_id'])) {
            $validatedData['unit_kerja_id'] = json_encode($validatedData['unit_kerja_id']);
        }
        // Jika jabatan_akademik_id juga disimpan sebagai JSON string di kolom TEXT,
        // lakukan encode juga di sini. (Saat ini disimpan di tabel pivot, jadi tidak perlu)
        // if (isset($validatedData['jabatan_akademik_id']) && is_array($validatedData['jabatan_akademik_id'])) {
        //     $validatedData['jabatan_akademik_id'] = json_encode($validatedData['jabatan_akademik_id']);
        // }
        // --- AKHIR PERUBAHAN UTAMA ---


        DB::beginTransaction();
        try {
            $old = $berita->getOriginal();

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

            // Fill the model with ALL validated data (which now includes encoded unit_kerja_id)
            // But still exclude files and _method as they are handled separately.
            $berita->fill($validatedData); // <-- PAKAI $validatedData LANGSUNG
            
            // Hapus field yang tidak disimpan langsung atau ditangani terpisah dari fill()
            unset($berita->gambar_berita, $berita->file_berita, $berita->jabatan_akademik_id); // Ini sudah ditangani
            // Juga, hapus _method dan clear flags jika ada di validatedData
            // if (isset($berita['_method'])) unset($berita['_method']);
            // if (isset($berita['gambar_berita_clear'])) unset($berita['gambar_berita_clear']);
            // if (isset($berita['file_berita_clear'])) unset($berita['file_berita_clear']);


            $berita->save(); // Save all changes to the database

            // Sync relation with jabatan akademik (using validated data - which is still an array here)
            $berita->jabatanAkademik()->sync($request->jabatan_akademik_id); // Gunakan $request->jabatan_akademik_id
                                                                           // karena $validatedData['jabatan_akademik_id']
                                                                           // sudah dimodifikasi di atas jika di-encode

            // Log changes
            $changes = array_diff_assoc($berita->toArray(), $old);
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
            \Log::error('Error updating berita: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui berita: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy($id)
    {
        $berita = SimpegBerita::find($id);

        if (!$berita) {
            return response()->json(['success' => false, 'message' => 'Berita tidak ditemukan'], 404);
        }

        DB::beginTransaction();
        try {
            $beritaData = $berita->toArray();
            
            // Soft delete berita (data masih ada di database, hanya ditandai dihapus)
            $berita->delete();

            // Log aktivitas
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
     */
    public function trash(Request $request)
    {
        $berita = SimpegBerita::onlyTrashed();

        // Filter berdasarkan judul
        if ($request->has('judul')) {
            $berita->where('judul', 'like', '%' . $request->judul . '%');
        }

        // Load relasi jabatan akademik
        $berita->with('jabatanAkademik');

        $berita = $berita->paginate(10);

        // Tambahkan URL untuk restore dan force delete
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
     */
    public function restore($id)
    {
        $berita = SimpegBerita::onlyTrashed()->find($id);

        if (!$berita) {
            return response()->json(['success' => false, 'message' => 'Berita yang dihapus tidak ditemukan'], 404);
        }

        $berita->restore();
        
        // Load relasi untuk response
        $berita->load('jabatanAkademik');

        // Log aktivitas
        ActivityLogger::log('restore', $berita, $berita->toArray());

        return response()->json([
            'success' => true,
            'data' => $berita,
            'message' => 'Berita berhasil dipulihkan'
        ]);
    }

    /**
     * Menghapus berita secara permanen dari database.
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
            
            // Hapus gambar dan file terkait
            if ($berita->gambar_berita && Storage::disk('public')->exists($berita->gambar_berita)) {
                Storage::disk('public')->delete($berita->gambar_berita);
            }
            
            if ($berita->file_berita && Storage::disk('public')->exists($berita->file_berita)) {
                Storage::disk('public')->delete($berita->file_berita);
            }
            
            // Hapus relasi dengan jabatan akademik
            $berita->jabatanAkademik()->detach();
            
            // Hapus berita secara permanen
            $berita->forceDelete();

            // Log aktivitas
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