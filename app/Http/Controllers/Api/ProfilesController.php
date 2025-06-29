<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\SimpegPegawai; // Pastikan model SimpegPegawai dapat diakses

/**
 * Controller untuk mengelola profil pegawai yang sedang login.
 */
class ProfilesController extends Controller
{
    /**
     * Mengambil data profil dari pengguna yang terotentikasi.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfile()
    {
        // Mengambil data pegawai yang sedang login
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan atau belum login.'], 404);
        }

        // Menambahkan URL lengkap untuk file_foto jika ada
        if ($pegawai->file_foto) {
            $pegawai->file_foto_url = Storage::url('pegawai/' . $pegawai->file_foto);
        } else {
            $pegawai->file_foto_url = null;
        }

        return response()->json([
            'success' => true,
            'message' => 'Data profil berhasil diambil.',
            'data' => $pegawai
        ]);
    }

    /**
     * Memperbarui informasi profil (email dan foto profil).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfile(Request $request)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan atau belum login.'], 404);
        }

        // Validasi input
        $validator = Validator::make($request->all(), [
            'email_pegawai' => [
                'sometimes', // Validasi hanya jika field ini ada di request
                'required',
                'email',
                // Pastikan email unik, kecuali untuk pegawai ini sendiri
                Rule::unique('simpeg_pegawai')->ignore($pegawai->id),
            ],
            'file_foto' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048', // Maks 2MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update email jika ada dalam request
        if ($request->has('email_pegawai')) {
            $pegawai->email_pegawai = $request->email_pegawai;
        }
        
        // Handle upload foto profil
        if ($request->hasFile('file_foto')) {
            // Hapus foto lama jika ada
            if ($pegawai->file_foto && Storage::disk('public')->exists('pegawai/' . $pegawai->file_foto)) {
                Storage::disk('public')->delete('pegawai/' . $pegawai->file_foto);
            }

            // Simpan foto baru
            $file = $request->file('file_foto');
            $fileName = $pegawai->nip . '_foto_' . time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('pegawai', $fileName, 'public');

            // Update nama file di database
            $pegawai->file_foto = $fileName;
        }

        $pegawai->save();

        // Tambahkan URL foto profil yang baru di respons
        if ($pegawai->file_foto) {
            $pegawai->file_foto_url = Storage::url('pegawai/' . $pegawai->file_foto);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => $pegawai
        ]);
    }

    /**
     * Mengubah password pengguna yang sedang login.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        $pegawai = Auth::user();

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan atau belum login.'], 404);
        }

        // Validasi input
        $validator = Validator::make($request->all(), [
            'password_lama' => 'required|string',
            'password_baru' => 'required|string|min:6|confirmed', // 'confirmed' akan mencocokkan dengan 'password_baru_confirmation'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Periksa apakah password lama sesuai
        if (!Hash::check($request->password_lama, $pegawai->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak sesuai.'
            ], 401);
        }

        // Update dengan password baru
        $pegawai->password = Hash::make($request->password_baru);
        $pegawai->save();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah.'
        ]);
    }
}

/**
 * =================================================================================
 * TAMBAHKAN ROUTE INI DI FILE routes/api.php
 * =================================================================================
 *
 * Pastikan route ini berada di dalam grup middleware 'auth:api' untuk memastikan
 * hanya pengguna yang sudah login yang bisa mengaksesnya.
 */
