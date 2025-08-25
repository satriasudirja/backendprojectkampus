<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegPegawai;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class BiodataController extends Controller
{
    /**
     * Menampilkan biodata lengkap pegawai yang sedang login
     */
    public function index(Request $request)
    {
        try {
            // Ambil user yang sedang login (instance SimpegPegawai)
            $pegawai = Auth::user()->pegawai;
            
            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            // Load semua relasi yang diperlukan
            $pegawai->load([
                'unitKerja',
                'statusAktif',
                'dataHubunganKerja.hubunganKerja',
                'dataJabatanFungsional.jabatanFungsional.jabatanAkademik',
                'dataPendidikanFormal',
                'dataPangkat.pangkat',
                'dataPangkat.jenisKenaikanPangkat',
                'dataPangkat.jenisSk',
                'dataJabatanAkademik.jabatanAkademik',
                'dataJabatanStruktural.jabatanStruktural'
            ]);

            // Format response data
            $biodataResponse = [
                'success' => true,
                'data' => [
                    'biodata_pribadi' => [
                        'id' => $pegawai->id,
                        'nip' => $pegawai->nip,
                        'nidn' => $pegawai->nidn,
                        'nuptk' => $pegawai->nuptk,
                        'nama_lengkap' => $pegawai->nama,
                        'gelar_depan' => $pegawai->gelar_depan,
                        'gelar_belakang' => $pegawai->gelar_belakang,
                        'jenis_kelamin' => $pegawai->jenis_kelamin,
                        'tempat_lahir' => $pegawai->tempat_lahir,
                        'tanggal_lahir' => $pegawai->tanggal_lahir,
                        'agama' => $pegawai->agama,
                        'golongan_darah' => $pegawai->golongan_darah,
                        'nama_ibu_kandung' => $pegawai->nama_ibu_kandung,
                        'tinggi_badan' => $pegawai->tinggi_badan,
                        'berat_badan' => $pegawai->berat_badan,
                    ],
                    'kontak' => [
                        'alamat_domisili' => $pegawai->alamat_domisili,
                        'alamat_kependudukan' => $pegawai->alamat_kependudukan,
                        'kota' => $pegawai->kota,
                        'provinsi' => $pegawai->provinsi,
                        'kode_pos' => $pegawai->kode_pos,
                        'no_handphone' => $pegawai->no_handphone,
                        'no_telepon_domisili' => $pegawai->no_telepon_domisili_kontak,
                        'no_telephone_kantor' => $pegawai->no_telephone_kantor,
                        'email_pribadi' => $pegawai->email_pribadi,
                        'jarak_rumah_domisili' => $pegawai->jarak_rumah_domisili,
                    ],
                    'dokumen_identitas' => [
                        'no_ktp' => $pegawai->no_ktp,
                        'no_kk' => $pegawai->no_kk,
                        'npwp' => $pegawai->npwp,
                        'no_kartu_bpjs' => $pegawai->no_kartu_bpjs,
                        'no_bpjs' => $pegawai->no_bpjs,
                        'no_bpjs_ketenagakerjaan' => $pegawai->no_bpjs_ketenagakerjaan,
                        'no_bpjs_pensiun' => $pegawai->no_bpjs_pensiun,
                        'no_kartu_pensiun' => $pegawai->no_kartu_pensiun,
                        'karpeg' => $pegawai->karpeg,
                    ],
                    'rekening_bank' => [
                        'no_rekening' => $pegawai->no_rekening,
                        'nama_bank' => $pegawai->nama_bank,
                        'cabang_bank' => $pegawai->cabang_bank,
                    ],
                    'unit_kerja' => $pegawai->unitKerja ? [
                        'id' => $pegawai->unitKerja->id,
                        'nama_unit' => $pegawai->unitKerja->nama_unit,
                        'kode_unit' => $pegawai->unitKerja->kode ?? null,
                    ] : null,
                    'status_kepegawaian' => [
                        'status_aktif' => $pegawai->statusAktif ? [
                            'id' => $pegawai->statusAktif->id,
                            'nama_status' => $pegawai->statusAktif->nama_status_aktif,
                            'kode' => $pegawai->statusAktif->kode,
                        ] : null,
                        'status_kerja' => $pegawai->status_kerja,
                        'hubungan_kerja_aktif' => $pegawai->dataHubunganKerja
                            ->where('tanggal_selesai', null)
                            ->first() ? [
                                'nama_hubungan_kerja' => $pegawai->dataHubunganKerja
                                    ->where('tanggal_selesai', null)
                                    ->first()->hubunganKerja->nama_hub_kerja ?? null,
                                'tanggal_mulai' => $pegawai->dataHubunganKerja
                                    ->where('tanggal_selesai', null)
                                    ->first()->tanggal_mulai ?? null,
                            ] : null,
                    ],
                    'pangkat_aktif' => $pegawai->dataPangkat
                        ->where('is_aktif', true)
                        ->first() ? [
                            'nama_pangkat' => $pegawai->dataPangkat
                                ->where('is_aktif', true)
                                ->first()->pangkat->nama_golongan ?? null,
                            'golongan' => $pegawai->dataPangkat
                                ->where('is_aktif', true)
                                ->first()->pangkat->pangkat ?? null,
                            'tmt_pangkat' => $pegawai->dataPangkat
                                ->where('is_aktif', true)
                                ->first()->tmt_pangkat ?? null,
                        ] : null,
                    'jabatan_akademik_aktif' => $pegawai->dataJabatanAkademik
                        ->where('tanggal_selesai', null)
                        ->first() ? [
                            'nama_jabatan' => $pegawai->dataJabatanAkademik
                                ->where('tanggal_selesai', null)
                                ->first()->jabatanAkademik->nama_jab_akademik ?? null,
                            'tanggal_mulai' => $pegawai->dataJabatanAkademik
                                ->where('tanggal_selesai', null)
                                ->first()->tanggal_mulai ?? null,
                        ] : null,
                    'jabatan_fungsional_aktif' => $pegawai->dataJabatanFungsional
                        ->where('tanggal_selesai', null)
                        ->first() ? [
                            'nama_jabatan' => $pegawai->dataJabatanFungsional
                                ->where('tanggal_selesai', null)
                                ->first()->jabatanFungsional->nama_jab_fungsional ?? null,
                            'tanggal_mulai' => $pegawai->dataJabatanFungsional
                                ->where('tanggal_selesai', null)
                                ->first()->tanggal_mulai ?? null,
                        ] : null,
                    'jabatan_struktural_aktif' => $pegawai->dataJabatanStruktural
                        ->where('tanggal_selesai', null)
                        ->first() ? [
                            'nama_jabatan' => $pegawai->dataJabatanStruktural
                                ->where('tanggal_selesai', null)
                                ->first()->jabatanStruktural->nama_jabatan ?? null,
                            'tanggal_mulai' => $pegawai->dataJabatanStruktural
                                ->where('tanggal_selesai', null)
                                ->first()->tanggal_mulai ?? null,
                        ] : null,
                    'files' => [
                        'file_ktp' => $pegawai->file_ktp ? url('storage/pegawai/' . $pegawai->file_ktp) : null,
                        'file_kk' => $pegawai->file_kk ? url('storage/pegawai/' . $pegawai->file_kk) : null,
                        'file_rekening' => $pegawai->file_rekening ? url('storage/pegawai/' . $pegawai->file_rekening) : null,
                        'file_karpeg' => $pegawai->file_karpeg ? url('storage/pegawai/' . $pegawai->file_karpeg) : null,
                        'file_npwp' => $pegawai->file_npwp ? url('storage/pegawai/' . $pegawai->file_npwp) : null,
                        'file_bpjs' => $pegawai->file_bpjs ? url('storage/pegawai/' . $pegawai->file_bpjs) : null,
                        'file_bpjs_ketenagakerjaan' => $pegawai->file_bpjs_ketenagakerjaan ? url('storage/pegawai/' . $pegawai->file_bpjs_ketenagakerjaan) : null,
                        'file_bpjs_pensiun' => $pegawai->file_bpjs_pensiun ? url('storage/pegawai/' . $pegawai->file_bpjs_pensiun) : null,
                        'file_tanda_tangan' => $pegawai->file_tanda_tangan ? url('storage/pegawai/' . $pegawai->file_tanda_tangan) : null,
                        'file_sertifikasi_dosen' => $pegawai->file_sertifikasi_dosen ? url('storage/pegawai/' . $pegawai->file_sertifikasi_dosen) : null,
                    ],
                ]
            ];

            return response()->json($biodataResponse);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan riwayat pendidikan pegawai yang sedang login
     */
    public function riwayatPendidikan(Request $request)
    {
        try {
            $pegawai = Auth::user();
            
            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            $riwayatPendidikan = $pegawai->dataPendidikanFormal()
                ->orderBy('tahun_lulus', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'pegawai' => [
                        'id' => $pegawai->id,
                        'nip' => $pegawai->nip,
                        'nama' => $pegawai->nama,
                    ],
                    'riwayat_pendidikan' => $riwayatPendidikan
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan riwayat pangkat pegawai yang sedang login
     */
    public function riwayatPangkat(Request $request)
    {
        try {
            $pegawai = Auth::user();
            
            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            $riwayatPangkat = $pegawai->dataPangkat()
                ->with([
                    'pangkat',
                    'jenisKenaikanPangkat',
                    'jenisSk',
                ])
                ->orderBy('tmt_pangkat', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'pegawai' => [
                        'id' => $pegawai->id,
                        'nip' => $pegawai->nip,
                        'nama' => $pegawai->nama,
                    ],
                    'riwayat_pangkat' => $riwayatPangkat->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'pangkat' => $item->pangkat ? [
                                'nama' => $item->pangkat->nama_golongan,
                                'golongan' => $item->pangkat->pangkat
                            ] : null,
                            'jenis_kenaikan' => $item->jenisKenaikanPangkat ? [
                                'nama' => $item->jenisKenaikanPangkat->nama_jenis_kenaikan_pangkat
                            ] : null,
                            'jenis_sk' => $item->jenisSk ? [
                                'nama' => $item->jenisSk->nama_jenis_sk
                            ] : null,
                            'tmt_pangkat' => $item->tmt_pangkat,
                            'no_sk' => $item->no_sk,
                            'tgl_sk' => $item->tgl_sk,
                            'pejabat_penetap' => $item->pejabat_penetap,
                            'masa_kerja' => [
                                'tahun' => $item->masa_kerja_tahun,
                                'bulan' => $item->masa_kerja_bulan
                            ],
                            'status' => [
                                'is_aktif' => $item->is_aktif,
                                'pengajuan' => $item->status_pengajuan
                            ],
                            'dokumen' => $item->file_pangkat ? [
                                'nama_file' => $item->file_pangkat,
                                'url' => url('storage/pegawai/' . $item->file_pangkat)
                            ] : null,
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan riwayat jabatan akademik pegawai yang sedang login
     */
    public function riwayatJabatanAkademik(Request $request)
    {
        try {
            $pegawai = Auth::user();
            
            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            $riwayatJabatanAkademik = $pegawai->dataJabatanAkademik()
                ->with('jabatanAkademik')
                ->orderBy('tanggal_mulai', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'pegawai' => [
                        'id' => $pegawai->id,
                        'nip' => $pegawai->nip,
                        'nama' => $pegawai->nama,
                    ],
                    'riwayat_jabatan_akademik' => $riwayatJabatanAkademik->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'jabatan_akademik' => $item->jabatanAkademik ? [
                                'id' => $item->jabatanAkademik->id,
                                'nama_jabatan' => $item->jabatanAkademik->nama_jab_akademik,
                            ] : null,
                            'tanggal_mulai' => $item->tanggal_mulai,
                            'tanggal_selesai' => $item->tanggal_selesai,
                            'no_sk' => $item->no_sk,
                            'tgl_sk' => $item->tgl_sk,
                            'file_sk' => $item->file_sk ? url('storage/pegawai/' . $item->file_sk) : null,
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan riwayat jabatan fungsional pegawai yang sedang login
     */
    public function riwayatJabatanFungsional(Request $request)
    {
        try {
            $pegawai = Auth::user();
            
            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            $riwayatJabatanFungsional = $pegawai->dataJabatanFungsional()
                ->with('jabatanFungsional.jabatanAkademik')
                ->orderBy('tanggal_mulai', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'pegawai' => [
                        'id' => $pegawai->id,
                        'nip' => $pegawai->nip,
                        'nama' => $pegawai->nama,
                    ],
                    'riwayat_jabatan_fungsional' => $riwayatJabatanFungsional->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'jabatan_fungsional' => $item->jabatanFungsional ? [
                                'id' => $item->jabatanFungsional->id,
                                'nama_jabatan' => $item->jabatanFungsional->nama_jab_fungsional,
                                'jabatan_akademik' => $item->jabatanFungsional->jabatanAkademik ? [
                                    'id' => $item->jabatanFungsional->jabatanAkademik->id,
                                    'nama_jabatan' => $item->jabatanFungsional->jabatanAkademik->nama_jab_akademik,
                                ] : null,
                            ] : null,
                            'tanggal_mulai' => $item->tanggal_mulai,
                            'tanggal_selesai' => $item->tanggal_selesai,
                            'no_sk' => $item->no_sk,
                            'tgl_sk' => $item->tgl_sk,
                            'file_sk' => $item->file_sk ? url('storage/pegawai/' . $item->file_sk) : null,
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan riwayat jabatan struktural pegawai yang sedang login
     */
    public function riwayatJabatanStruktural(Request $request)
    {
        try {
            $pegawai = Auth::user();
            
            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            $riwayatJabatanStruktural = $pegawai->dataJabatanStruktural()
                ->with('jabatanStruktural')
                ->orderBy('tanggal_mulai', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'pegawai' => [
                        'id' => $pegawai->id,
                        'nip' => $pegawai->nip,
                        'nama' => $pegawai->nama,
                    ],
                    'riwayat_jabatan_struktural' => $riwayatJabatanStruktural->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'jabatan_struktural' => $item->jabatanStruktural ? [
                                'id' => $item->jabatanStruktural->id,
                                'nama_jabatan' => $item->jabatanStruktural->nama_jabatan,
                                'level' => $item->jabatanStruktural->level,
                            ] : null,
                            'tanggal_mulai' => $item->tanggal_mulai,
                            'tanggal_selesai' => $item->tanggal_selesai,
                            'no_sk' => $item->no_sk,
                            'tgl_sk' => $item->tgl_sk,
                            'file_sk' => $item->file_sk ? url('storage/pegawai/' . $item->file_sk) : null,
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan riwayat hubungan kerja pegawai yang sedang login
     */
    public function riwayatHubunganKerja(Request $request)
    {
        try {
            $pegawai = Auth::user();
            
            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            $riwayatHubunganKerja = $pegawai->dataHubunganKerja()
                ->with('hubunganKerja')
                ->orderBy('tanggal_mulai', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'pegawai' => [
                        'id' => $pegawai->id,
                        'nip' => $pegawai->nip,
                        'nama' => $pegawai->nama,
                    ],
                    'riwayat_hubungan_kerja' => $riwayatHubunganKerja->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'hubungan_kerja' => $item->hubunganKerja ? [
                                'id' => $item->hubunganKerja->id,
                                'nama_hubungan_kerja' => $item->hubunganKerja->nama_hub_kerja,
                            ] : null,
                            'tanggal_mulai' => $item->tanggal_mulai,
                            'tanggal_selesai' => $item->tanggal_selesai,
                            'no_sk' => $item->no_sk,
                            'tgl_sk' => $item->tgl_sk,
                            'file_sk' => $item->file_sk ? url('storage/pegawai/' . $item->file_sk) : null,
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Menampilkan rekap kehadiran pegawai yang sedang login
     */
    public function rekapKehadiran(Request $request)
    {
        try {
            $pegawai = Auth::user();
            
            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi'
                ], 401);
            }

            $rekap = $pegawai->absensiRecord()
                ->select(
                    DB::raw('YEAR(tanggal) as tahun'),
                    DB::raw('MONTH(tanggal) as bulan'),
                    DB::raw('COUNT(*) as total_kehadiran'),
                    DB::raw('SUM(CASE WHEN status = "hadir" THEN 1 ELSE 0 END) as hadir'),
                    DB::raw('SUM(CASE WHEN status = "sakit" THEN 1 ELSE 0 END) as sakit'),
                    DB::raw('SUM(CASE WHEN status = "izin" THEN 1 ELSE 0 END) as izin'),
                    DB::raw('SUM(CASE WHEN status = "cuti" THEN 1 ELSE 0 END) as cuti'),
                    DB::raw('SUM(CASE WHEN status = "alpa" THEN 1 ELSE 0 END) as alpa')
                )
                ->groupBy('tahun', 'bulan')
                ->orderBy('tahun', 'desc')
                ->orderBy('bulan', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'pegawai' => [
                        'id' => $pegawai->id,
                        'nip' => $pegawai->nip,
                        'nama' => $pegawai->nama,
                    ],
                    'rekap_kehadiran' => $rekap
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
  public function updateBiodata(Request $request)
    {
        // 1. Dapatkan model Pegawai yang sedang terotentikasi secara langsung
        $pegawai = Auth::user();

        // Cukup periksa apakah autentikasi berhasil
        if (!$pegawai) {
            return response()->json([
                'success' => false, 
                'message' => 'Data pegawai untuk pengguna ini tidak ditemukan atau tidak terautentikasi.'
            ], 404);
        }
        // Variabel $pegawai sudah merupakan model yang benar, tidak perlu mencari $user->pegawai lagi.

        // 2. Definisikan aturan validasi
        $validationRules = [
            // Field yang wajib diisi (tidak boleh kosong)
            'nama' => 'required|string|max:255',
            'tanggal_lahir' => 'required|date',
            'tempat_lahir' => 'required|string|max:30',
            'agama' => 'required|string|max:20',
            'jenis_kelamin' => 'required|string|max:30',
            // Pastikan tabel simpeg_status_pernikahan juga menggunakan UUID untuk kolom 'id'
            'email_pribadi' => 'required|email|max:50',
            'no_ktp' => 'required|string|max:30',
            'no_kk' => 'required|string|max:16',
            'alamat_domisili' => 'required|string|max:255',
            'no_handphone' => 'required|string|max:20',

            // Field yang boleh kosong (nullable)
            'email_pegawai' => 'nullable|email|max:50',
            'golongan_darah' => 'nullable|string|max:10',
            'warga_negara' => 'nullable|string|max:30',
            'kecamatan' => 'nullable|string|max:50',
            'kota' => 'nullable|string|max:30',
            'provinsi' => 'nullable|string|max:30',
            'kode_pos' => 'nullable|string|max:5',
            'no_whatsapp' => 'nullable|string|max:20',
            'cabang_bank' => 'nullable|string|max:100',
            'nama_bank' => 'nullable|string|max:100',
            'no_rekening' => 'nullable|string|max:50',
            'atas_nama_rekening' => 'nullable|string|max:100',
        ];

        // 3. Lakukan validasi
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ambil data yang sudah tervalidasi
        $validatedData = $validator->validated();
        
        // Simpan data lama untuk logging
        $oldData = $pegawai->getAttributes();

        DB::beginTransaction();
        try {
            // 4. Update data pegawai
            $pegawai->update($validatedData);

            // 5. Log aktivitas
            $changes = array_diff_assoc($pegawai->getAttributes(), $oldData);
            if (!empty($changes)) {
                ActivityLogger::log('update-biodata', $pegawai, $changes);
            }
            
            // 6. Commit transaksi
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Biodata berhasil diperbarui.',
                'data' => $pegawai->fresh() // Kirim kembali data terbaru
            ]);

        } catch (\Exception $e) {
            // 7. Rollback jika terjadi error
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui biodata: ' . $e->getMessage()
            ], 500);
        }
    }

    
}