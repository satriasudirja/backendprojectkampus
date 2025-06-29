<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SimpegPegawai;
use App\Models\SimpegDataJabatanAkademik;  
use App\Models\SimpegJabatanAkademik;  
use App\Models\SimpegUnitKerja;
use App\Models\SimpegStatusAktif;
use App\Models\HubunganKerja;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class PegawaiController extends Controller
{
    public function index(Request $request)
    {
        $query = SimpegPegawai::with([
            'unitKerja',
            'statusAktif',
            'dataHubunganKerja.hubunganKerja',
            'dataJabatanFungsional.jabatanFungsional.jabatanAkademik'
        ]);

        // Filtering
        if ($request->has('nip') && !empty($request->nip)) {
            $query->where('nip', 'like', '%' . $request->nip . '%');
        }

        if ($request->has('nidn') && !empty($request->nidn)) {
            $query->where('nidn', 'like', '%' . $request->nidn . '%');
        }

        if ($request->has('nuptk') && !empty($request->nuptk)) {
            $query->where('nuptk', 'like', '%' . $request->nuptk . '%');
        }

        if ($request->has('nama') && !empty($request->nama)) {
            $query->where('nama', 'like', '%' . $request->nama . '%');
        }

        if ($request->has('unit_kerja_id') && !empty($request->unit_kerja_id)) {
            $query->where('unit_kerja_id', $request->unit_kerja_id);
        }

        if ($request->has('status_aktif_id') && !empty($request->status_aktif_id)) {
            $query->where('status_aktif_id', $request->status_aktif_id);
        }

        if ($request->has('hubungan_kerja_id') && !empty($request->hubungan_kerja_id)) {
            $query->whereHas('dataHubunganKerja', function ($q) use ($request) {
                $q->where('hubungan_kerja_id', $request->hubungan_kerja_id);
            });
        }

        if ($request->has('jabatan_fungsional_id') && !empty($request->jabatan_fungsional_id)) {
            $query->whereHas('dataJabatanFungsional', function ($q) use ($request) {
                $q->where('jabatan_fungsional_id', $request->jabatan_fungsional_id);
            });
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('nip', 'like', '%' . $search . '%')
                  ->orWhere('nidn', 'like', '%' . $search . '%')
                  ->orWhere('nuptk', 'like', '%' . $search . '%');
            });
        }

        // Pagination
        $perPage = $request->has('per_page') ? (int)$request->per_page : 10;
        $pegawai = $query->orderBy('nama', 'asc')->paginate($perPage);

        // Get prefix from URL
        $prefix = $request->segment(2);

        // Transform data to simplified table view
        $pegawaiData = $pegawai->getCollection()->map(function ($item) use ($prefix) {
            $terhubung_sister = false; // Placeholder, replace with actual logic
            
            // Debug the unitKerja relationship
            $unit_kerja_nama = null;
            if ($item->unitKerja) {
                $unit_kerja_nama = $item->unitKerja->nama_unit;
            } elseif ($item->unit_kerja_id) {
                // If relationship failed but ID exists, try to get directly
                $unitKerja = SimpegUnitKerja::find($item->unit_kerja_id);
                $unit_kerja_nama = $unitKerja ? $unitKerja->nama_unit : 'Unit Kerja #' . $item->unit_kerja_id;
            } else {
                $unit_kerja_nama = 'Tidak Ada';
            }
            
            return [
                'id' => $item->id, // Keep the ID for action purposes
                'nip' => $item->nip,
                'nidn' => $item->nidn,
                'nuptk' => $item->nuptk ?? '',
                'nama_pegawai' => $item->nama,
                'unit_kerja' => $unit_kerja_nama,
                'status' => $item->statusAktif ? $item->statusAktif->kode : '-',
                'aksi' => [
                    'detail_url' => url("/api/{$prefix}/pegawai/" . $item->id),
                    'delete_url' => url("/api/{$prefix}/pegawai/" . $item->id),
                    'update_status_url' => url("/api/{$prefix}/pegawai/update-status/" . $item->id),
                  'riwayat' => [
    'riwayat_pendidikan' => url("/api/pegawai/$item->id/riwayat-pendidikan-formal"),
    'riwayat_pekerjaan' => url("/api/pegawai/$item->id/riwayat-pekerjaan"),
    'riwayat_diklat' => url("/api/pegawai/$item->id/riwayat-diklat"),
    'riwayat_pangkat' => url("/api/pegawai/$item->id/riwayat-pangkat"),
    'riwayat_jabatan_struktural' => url("/api/pegawai/$item->id/riwayat-jabatan-struktural"),
    'riwayat_jabatan_akademik' => url("/api/pegawai/$item->id/riwayat-jabatan-akademik"),
    'riwayat_hubungan_kerja' => url("/api/pegawai/$item->id/riwayat-hubungan-kerja"),
    'riwayat_data_orang_tua' => url("/api/pegawai/$item->id/riwayat-data-orang-tua"),
    'riwayat_data_pasangan' => url("/api/pegawai/$item->id/riwayat-data-pasangan"),
    'riwayat_data_anak' => url("/api/pegawai/$item->id/riwayat-data-anak"),
    'rekap_kehadiran' => url("/api/pegawai/$item->id/rekap-kehadiran") // Tambahkan jika ada route-nya
]
                ]
            ];
        });

        // Create a new paginator with the transformed data
        $paginationData = $pegawai->toArray();
        unset($paginationData['data']);
        
        // Get filter options for dropdowns
        $unitKerja = SimpegUnitKerja::select('id', 'nama_unit')->orderBy('nama_unit', 'asc')->get();
        $statusAktif = SimpegStatusAktif::select('id', 'nama_status_aktif')->orderBy('nama_status_aktif', 'asc')->get();
        $hubunganKerja = HubunganKerja::select('id', 'nama_hub_kerja')->orderBy('nama_hub_kerja', 'asc')->get();
        
        return response()->json([
            'success' => true,
            'data' => array_merge(['data' => $pegawaiData], $paginationData),
            'filters' => [
                'unit_kerja' => $unitKerja,
                'status_aktif' => $statusAktif,
                'hubungan_kerja' => $hubunganKerja,
            ],
            'table_rows_options' => [10, 25, 50, 100],
            'table_columns' => [
                ['field' => 'nip', 'label' => 'NIP'],
                ['field' => 'nidn', 'label' => 'NIDN'],
                ['field' => 'nuptk', 'label' => 'NUPTK'],
                ['field' => 'nama_pegawai', 'label' => 'Nama Pegawai'],
                ['field' => 'unit_kerja', 'label' => 'Unit Kerja'],
                ['field' => 'status', 'label' => 'Status'],
                ['field' => 'terhubung_sister', 'label' => 'Terhubung Sister'],
                ['field' => 'aksi', 'label' => 'Aksi'],
            ],
            'tambah_pegawai_url' => url("/api/{$prefix}/pegawai/create")
        ]);
    }

    public function show(Request $request, $id)
    {
        $pegawai = SimpegPegawai::with([
            'unitKerja',
            'statusAktif',
            'dataHubunganKerja.hubunganKerja',
            'dataJabatanFungsional.jabatanFungsional.jabatanAkademik',
            'dataPendidikanFormal',
            'dataPangkat',
            'dataJabatanAkademik',
            'dataJabatanFungsional',
            'dataJabatanStruktural',
            'absensiRecord'
      ])->find($id);


        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $prefix = $request->segment(2);

        return response()->json([
            'success' => true,
            'data' => $pegawai,
            'update_url' => url("/api/{$prefix}/pegawai/" . $pegawai->id),
            'delete_url' => url("/api/{$prefix}/pegawai/" . $pegawai->id),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'nip' => 'required|string|max:50|unique:simpeg_pegawai,nip',
            'nidn' => 'nullable|string|max:50',
            'nuptk' => 'nullable|string|max:50',
            'nama' => 'required|string|max:255',
            'unit_kerja_id' => 'required|exists:simpeg_unit_kerja,id',
            'status_aktif_id' => 'required|exists:simpeg_status_aktif,id',
            'jenis_kelamin' => 'required|string|max:30',
            'tempat_lahir' => 'required|string|max:30',
            'tanggal_lahir' => 'required|date',
            'nama_ibu_kandung' => 'nullable|string|max:50', // Updated to nullable
            'alamat_domisili' => 'required|string|max:255',
            'agama' => 'required|string|max:20',
            'kota' => 'required|string|max:30',
            'provinsi' => 'required|string|max:30',
            'kode_pos' => 'required|string|max:5',
            'no_handphone' => 'required|string|max:20',
            'no_whatsapp' => 'nullable|string|max:20',        // Added
            'no_kk' => 'required|string|max:16',
            'email_pribadi' => 'required|email|max:50',
            'email_pegawai' => 'nullable|email|max:50',       // Added
            'no_ktp' => 'required|string|max:30',
            'status_kerja' => 'required|string|max:50',
            'nomor_polisi' => 'nullable|string|max:20',       // Added
            'jenis_kendaraan' => 'nullable|string|max:50',    // Added
            'merk_kendaraan' => 'nullable|string|max:50',     // Added
            'hubungan_kerja_id' => 'nullable|exists:hubungan_kerja,id',
            'jabatan_fungsional_id' => 'nullable|exists:simpeg_jabatan_fungsional,id',
            'password' => 'sometimes|required|min:6',
            // Tambahkan validasi untuk field yang sering NULL
            //'user_id' => 'nullable|exists:simpeg_jabatan_akademik,id',
            'kode_status_pernikahan' => 'nullable|exists:simpeg_status_pernikahan,id',
            'jabatan_akademik_id' => 'nullable|exists:simpeg_jabatan_akademik,id',
            'suku_id' => 'nullable|exists:simpeg_suku,id',
        ]);

        DB::beginTransaction();
        try {
            $roleIdToStore = null;

            if($request->has('jabatan_akademik_id') && !empty($request->jabatan_akademik_id)){
                $jabatanAkademik = SimpegJabatanAkademik::find($request->jabatan_akademik_id);
            
                if($jabatanAkademik){
                    $roleIdToStore = $jabatanAkademik->role_id;
                }
            }
            

            // Create pegawai with updated fields
            $pegawai = SimpegPegawai::create([
                'nip' => $request->nip,
                'nidn' => $request->nidn,
                'nuptk' => $request->nuptk,
                'nama' => $request->nama,
                'unit_kerja_id' => $request->unit_kerja_id,
                'status_aktif_id' => $request->status_aktif_id,
                'jenis_kelamin' => $request->jenis_kelamin,
                'tempat_lahir' => $request->tempat_lahir,
                'tanggal_lahir' => $request->tanggal_lahir,
                'nama_ibu_kandung' => $request->nama_ibu_kandung,
                'alamat_domisili' => $request->alamat_domisili,
                'agama' => $request->agama,
                'kota' => $request->kota,
                'provinsi' => $request->provinsi,
                'kode_pos' => $request->kode_pos,
                'no_handphone' => $request->no_handphone,
                'no_whatsapp' => $request->no_whatsapp,               // Added
                'no_kk' => $request->no_kk,
                'email_pribadi' => $request->email_pribadi,
                'email_pegawai' => $request->email_pegawai,           // Added
                'no_ktp' => $request->no_ktp,
                'status_kerja' => $request->status_kerja,
                'nomor_polisi' => $request->nomor_polisi,             // Added
                'jenis_kendaraan' => $request->jenis_kendaraan,       // Added
                'merk_kendaraan' => $request->merk_kendaraan,         // Added
                'password' => $request->has('password') 
                    ? bcrypt($request->password) 
                    : bcrypt(date('dmY', strtotime($request->tanggal_lahir))),
                'gelar_depan' => $request->gelar_depan,
                'gelar_belakang' => $request->gelar_belakang,
                'golongan_darah' => $request->golongan_darah,
                'jarak_rumah_domisili' => $request->jarak_rumah_domisili,
                'npwp' => $request->npwp,
                'no_kartu_pensiun' => $request->no_kartu_pensiun,
                'kepemilikan_nohp_utama' => $request->kepemilikan_nohp_utama,
                'alamat_kependudukan' => $request->alamat_kependudukan,
                'no_rekening' => $request->no_rekening,
                'cabang_bank' => $request->cabang_bank,
                'nama_bank' => $request->nama_bank,
                'karpeg' => $request->karpeg,
                'no_bpjs' => $request->no_bpjs,
                'no_bpjs_ketenagakerjaan' => $request->no_bpjs_ketenagakerjaan,
                'tinggi_badan' => $request->tinggi_badan,
                'berat_badan' => $request->berat_badan,
                'modified_by' => auth()->id(),
                'modified_dt' => now(),
                // Tambahkan field yang sering NULL
                'user_id' => $roleIdToStore,
                'kode_status_pernikahan' => $request->kode_status_pernikahan,
                'jabatan_akademik_id' => $request->jabatan_akademik_id,
                'suku_id' => $request->suku_id,
                
                // Removed fields (commented out):
                // 'no_kartu_bpjs' => $request->no_kartu_bpjs,
                // 'no_bpjs_pensiun' => $request->no_bpjs_pensiun,
                // 'no_telepon_domisili_kontak' => $request->no_telepon_domisili_kontak,
                // 'no_telephone_kantor' => $request->no_telephone_kantor,
            ]);

            // File uploads
            $this->handleFileUploads($request, $pegawai);

            // If hubungan_kerja_id is provided, create relation
            if ($request->has('hubungan_kerja_id') && !empty($request->hubungan_kerja_id)) {
                $pegawai->dataHubunganKerja()->create([
                    'hubungan_kerja_id' => $request->hubungan_kerja_id,
                    'tanggal_mulai' => now(),
                    'keterangan' => $request->hubungan_kerja_keterangan ?? 'Initial entry',
                    'created_by' => auth()->id(),
                ]);
            }

            // If jabatan_fungsional_id is provided
            if ($request->has('jabatan_fungsional_id') && !empty($request->jabatan_fungsional_id)) {
                $pegawai->dataJabatanFungsional()->create([
                    'jabatan_fungsional_id' => $request->jabatan_fungsional_id,
                    'tanggal_mulai' => now(),
                    'keterangan' => $request->jabatan_fungsional_keterangan ?? 'Initial entry',
                    'created_by' => auth()->id(),
                ]);
            }

            ActivityLogger::log('create', $pegawai, $pegawai->toArray());
            
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $pegawai,
                'message' => 'Pegawai berhasil ditambahkan'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan pegawai: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        // Ambil data pegawai saat ini untuk digunakan sebagai nilai default
        $currentData = $pegawai->toArray();

        // Validasi dinamis berdasarkan field yang dikirim
        $validationRules = [];
        
        // Field yang wajib divalidasi jika dikirim
        $requiredFields = [
            'nip' => 'string|max:50|unique:simpeg_pegawai,nip,'.$id,
            'nama' => 'string|max:255',
            'unit_kerja_id' => 'exists:simpeg_unit_kerja,id',
            'status_aktif_id' => 'exists:simpeg_status_aktif,id'
        ];
        
        // Field yang boleh kosong (nullable) - Updated
        $nullableFields = [
            'nidn' => 'string|max:50',
            'nuptk' => 'string|max:50',
            'jenis_kelamin' => 'string|max:30',
            'tempat_lahir' => 'string|max:30',
            'tanggal_lahir' => 'date',
            'nama_ibu_kandung' => 'string|max:50',
            'alamat_domisili' => 'string|max:255',
            'agama' => 'string|max:20',
            'kota' => 'string|max:30',
            'provinsi' => 'string|max:30',
            'kode_pos' => 'string|max:5',
            'no_handphone' => 'string|max:20',
            'no_whatsapp' => 'string|max:20',               // Added
            'no_kk' => 'string|max:16',
            'email_pribadi' => 'email|max:50',
            'email_pegawai' => 'email|max:50',              // Added
            'no_ktp' => 'string|max:30',
            'status_kerja' => 'string|max:50',
            'nomor_polisi' => 'string|max:20',              // Added
            'jenis_kendaraan' => 'string|max:50',           // Added
            'merk_kendaraan' => 'string|max:50',            // Added
            'hubungan_kerja_id' => 'exists:hubungan_kerja,id',
            'jabatan_fungsional_id' => 'exists:simpeg_jabatan_fungsional,id',
            'user_id' => 'exists:simpeg_jabatan_akademik,id',
            'kode_status_pernikahan' => 'exists:simpeg_status_pernikahan,id',
            'jabatan_akademik_id' => 'exists:simpeg_jabatan_akademik,id',
            'suku_id' => 'exists:simpeg_suku,id',
            'password' => 'min:6'
        ];
        
        // Tambahkan rule untuk field yang ada di request
        foreach ($requiredFields as $field => $rule) {
            if ($request->has($field)) {
                $validationRules[$field] = $rule;
            }
        }
        
        foreach ($nullableFields as $field => $rule) {
            if ($request->has($field)) {
                $validationRules[$field] = 'nullable|'.$rule;
            }
        }
        
        // Validasi request
        $validatedData = $request->validate($validationRules);
        
        $old = $pegawai->getOriginal();

        DB::beginTransaction();
        try {
            // Siapkan data untuk update
            $updateData = [
                'modified_by' => auth()->id() ?? 1,
                'modified_dt' => now(),
            ];
            
            // Daftar semua field yang mungkin diupdate - Updated
            $allFields = [
                'nip', 'nidn', 'nuptk', 'nama', 'unit_kerja_id', 'status_aktif_id',
                'user_id', 'kode_status_pernikahan', 'jabatan_akademik_id', 'suku_id',
                'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'nama_ibu_kandung',
                'no_sk_capeg', 'tanggal_sk_capeg', 'golongan_capeg', 'tmt_capeg',
                'no_sk_pegawai', 'tanggal_sk_pegawai', 'alamat_domisili', 'agama',
                'golongan_darah', 'kota', 'provinsi', 'kode_pos',
                'no_handphone', 'no_whatsapp', 'no_kk', 'email_pribadi', 'email_pegawai',
                'no_ktp', 'jarak_rumah_domisili', 'npwp', 'file_sertifikasi_dosen', 
                'no_kartu_pensiun', 'status_kerja', 'kepemilikan_nohp_utama', 
                'alamat_kependudukan', 'no_rekening', 'cabang_bank', 'nama_bank', 'karpeg',
                'no_bpjs', 'no_bpjs_ketenagakerjaan', 'tinggi_badan', 'berat_badan', 
                'gelar_depan', 'gelar_belakang', 'nomor_polisi', 'jenis_kendaraan', 'merk_kendaraan'
                
                // Removed fields (commented out):
                // 'no_kartu_bpjs', 'no_bpjs_pensiun', 'no_telepon_domisili_kontak', 'no_telephone_kantor'
            ];
            
            // Tambahkan ke updateData semua field yang ada di request
            foreach ($allFields as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->$field;
                }
            }
            
            // Tangani password secara khusus
            if ($request->has('password') && !empty($request->password)) {
                $updateData['password'] = bcrypt($request->password);
            }
            
            // Update pegawai
            $pegawai->update($updateData);

            // Handle file uploads
            $this->handleFileUploads($request, $pegawai);

            // Update relations if needed
            if ($request->has('hubungan_kerja_id') && !empty($request->hubungan_kerja_id)) {
                // End current relationship
                $pegawai->dataHubunganKerja()
                    ->whereNull('tanggal_selesai')
                    ->update([
                        'tanggal_selesai' => now(),
                        'updated_by' => auth()->id() ?? 1
                    ]);
                
                // Create new relationship
                $pegawai->dataHubunganKerja()->create([
                    'hubungan_kerja_id' => $request->hubungan_kerja_id,
                    'tanggal_mulai' => now(),
                    'keterangan' => $request->hubungan_kerja_keterangan ?? 'Updated via API',
                    'created_by' => auth()->id() ?? 1,
                ]);
            }

            // Similar pattern for jabatan_fungsional
            if ($request->has('jabatan_fungsional_id') && !empty($request->jabatan_fungsional_id)) {
                $pegawai->dataJabatanFungsional()
                    ->whereNull('tanggal_selesai')
                    ->update([
                        'tanggal_selesai' => now(),
                        'updated_by' => auth()->id() ?? 1
                    ]);
                
                $pegawai->dataJabatanFungsional()->create([
                    'jabatan_fungsional_id' => $request->jabatan_fungsional_id,
                    'tanggal_mulai' => now(),
                    'keterangan' => $request->jabatan_fungsional_keterangan ?? 'Updated via API',
                    'created_by' => auth()->id() ?? 1,
                ]);
            }

            // Check if unit_kerja_id has changed
            if ($request->has('unit_kerja_id') && $old['unit_kerja_id'] != $request->unit_kerja_id) {
                // End current unit kerja history
                $pegawai->riwayatUnitKerja()
                    ->whereNull('tanggal_selesai')
                    ->update([
                        'tanggal_selesai' => now(),
                        'updated_by' => auth()->id() ?? 1
                    ]);
                
                // Create new unit kerja history
                $pegawai->riwayatUnitKerja()->create([
                    'unit_kerja_id' => $request->unit_kerja_id,
                    'tanggal_mulai' => now(),
                    'keterangan' => $request->unit_kerja_keterangan ?? 'Updated via API',
                    'created_by' => auth()->id() ?? 1,
                ]);
            }

            // Jika ada perubahan jabatan_akademik_id
            if ($request->has('jabatan_akademik_id') && $old['jabatan_akademik_id'] != $request->jabatan_akademik_id) {
                // Akhiri riwayat jabatan akademik saat ini
                $pegawai->dataJabatanAkademik()
                    ->whereNull('tanggal_selesai')
                    ->update([
                        'tanggal_selesai' => now(),
                        'updated_by' => auth()->id() ?? 1
                    ]);
                
                // Buat riwayat jabatan akademik baru
                $pegawai->dataJabatanAkademik()->create([
                    'jabatan_akademik_id' => $request->jabatan_akademik_id,
                    'tanggal_mulai' => now(),
                    'keterangan' => $request->jabatan_akademik_keterangan ?? 'Updated via API',
                    'created_by' => auth()->id() ?? 1,
                ]);
            }

            $changes = array_diff_assoc($pegawai->toArray(), $old);
            ActivityLogger::log('update', $pegawai, $changes);
            
            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $pegawai,
                'message' => 'Pegawai berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui pegawai: ' . $e->getMessage()
            ], 500);
        }
    }

    private function handleFileUploads(Request $request, SimpegPegawai $pegawai)
    {
        $fileFields = [
            'file_ktp', 'file_kk', 'file_rekening', 'file_karpeg', 'file_npwp',
            'file_bpjs', 'file_bpjs_ketenagakerjaan', 'file_tanda_tangan', 'file_sertifikasi_dosen'
            // Removed: 'file_bpjs_pensiun'
        ];
        
        foreach ($fileFields as $field) {
            if ($request->hasFile($field)) {
                $file = $request->file($field);
                $fileName = $pegawai->nip . '_' . $field . '_' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('pegawai', $fileName, 'public');
                $pegawai->update([$field => $fileName]);
            }
        }
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'pegawai_ids' => 'required|array',
            'pegawai_ids.*' => 'exists:simpeg_pegawai,id',
            'status_aktif_id' => 'required|exists:simpeg_status_aktif,id'
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->pegawai_ids as $id) {
                $pegawai = SimpegPegawai::find($id);
                if (!$pegawai) continue;
                
                $old = $pegawai->getOriginal();
                
                $pegawai->update([
                    'status_aktif_id' => $request->status_aktif_id
                ]);
                
                $changes = array_diff_assoc($pegawai->toArray(), $old);
                ActivityLogger::log('update', $pegawai, $changes);
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Status pegawai berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status pegawai: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        // Validasi input - menerima pegawai_id (single) atau pegawai_ids (array)
        $validator = Validator::make($request->all(), [
            'pegawai_id' => 'nullable|exists:simpeg_pegawai,id',
            'pegawai_ids' => 'nullable|array',
            'pegawai_ids.*' => 'exists:simpeg_pegawai,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Inisialisasi array untuk menyimpan ID yang akan dihapus
        $pegawaiIds = [];
        
        // Cek apakah ada pegawai_id tunggal
        if ($request->has('pegawai_id')) {
            $pegawaiIds[] = $request->pegawai_id;
        }
        
        // Cek apakah ada pegawai_ids array
        if ($request->has('pegawai_ids')) {
            $pegawaiIds = array_merge($pegawaiIds, $request->pegawai_ids);
        }
        
        // Jika tidak ada ID yang valid
        if (empty($pegawaiIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada ID pegawai valid yang diberikan. Gunakan pegawai_id (single) atau pegawai_ids (array).'
            ], 400);
        }
        
        DB::beginTransaction();
        try {
            $deletedCount = 0;
            
            foreach ($pegawaiIds as $pegawaiId) {
                $pegawai = SimpegPegawai::find($pegawaiId);
                if (!$pegawai) continue;
                
                $pegawaiData = $pegawai->toArray();
                
                $pegawai->delete(); // Using SoftDeletes
                
                ActivityLogger::log('delete', $pegawai, $pegawaiData);
                
                $deletedCount++;
            }
            
            DB::commit();
            
            // Berikan respons yang sesuai dengan konteks
            if ($deletedCount === 1) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pegawai berhasil dihapus'
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => $deletedCount . ' pegawai berhasil dihapus'
                ]);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus pegawai: ' . $e->getMessage()
            ], 500);
        }
    }

    public function riwayatUnitKerja($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $riwayat = $pegawai->riwayatUnitKerja()->with('unitKerja')->orderBy('tanggal_mulai', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'riwayat' => $riwayat
            ]
        ]);
    }

    public function riwayatPendidikan($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $riwayat = $pegawai->dataPendidikanFormal()->orderBy('tahun_lulus', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'riwayat' => $riwayat
            ]
        ]);
    }

    public function riwayatPangkat($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai tidak ditemukan'
            ], 404);
        }

        $riwayat = $pegawai->dataPangkat()
            ->with([
                'pangkat',
                'jenisKenaikanPangkat',
                'jenisSk',
                'jabatanStruktural' => function($query) {
                    $query->with('pangkat');
                }
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
                    'unit_kerja' => $pegawai->unitKerja ? $pegawai->unitKerja->nama_unit : null,
                    'pangkat_terakhir' => $pegawai->dataPangkat()
                        ->where('is_aktif', true)
                        ->with('pangkat')
                        ->first()
                ],
                'riwayat' => $riwayat->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'pangkat' => $item->pangkat ? [
                            'id' => $item->pangkat->id,
                            'nama' => $item->pangkat->nama_golongan,
                            'golongan' => $item->pangkat->pangkat
                        ] : null,
                        'jenis_kenaikan' => $item->jenisKenaikanPangkat ? [
                            'id' => $item->jenisKenaikanPangkat->id,
                            'nama' => $item->jenisKenaikanPangkat->nama_jenis_kenaikan_pangkat
                        ] : null,
                        'jenis_sk' => $item->jenisSk ? [
                            'id' => $item->jenisSk->id,
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
                        'jabatan_struktural' => $item->jabatanStruktural ? [
                            'id' => $item->jabatanStruktural->id,
                            'nama' => $item->jabatanStruktural->singkatan,
                            'pangkat_minimal' => $item->jabatanStruktural->pangkat ? [
                                'id' => $item->jabatanStruktural->pangkat->id,
                                'nama' => $item->jabatanStruktural->pangkat->nama_golongan
                            ] : null
                        ] : null,
                        'status' => [
                            'is_aktif' => $item->is_aktif,
                            'pengajuan' => $item->status_pengajuan
                        ],
                        'dokumen' => $item->file_pangkat ? [
                            'nama_file' => $item->file_pangkat,
                            'url' => url('storage/pegawai/' . $item->file_pangkat)
                        ] : null,
                        'tanggal_input' => $item->tgl_input
                    ];
                })
            ]
        ]);
    }

    public function riwayatFungsional($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $riwayat = $pegawai->dataJabatanAkademik()->orderBy('tanggal_mulai', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'riwayat' => $riwayat
            ]
        ]);
    }

    public function riwayatJenjangFungsional($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $riwayat = $pegawai->dataJabatanFungsional()->with('jabatanFungsional.jabatanAkademik')
            ->orderBy('tanggal_mulai', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'riwayat' => $riwayat
            ]
        ]);
    }

    public function riwayatJabatanStruktural($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $riwayat = $pegawai->dataJabatanStruktural()->orderBy('tanggal_mulai', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'riwayat' => $riwayat
            ]
        ]);
    }

    public function riwayatHubunganKerja($id)
    {
        $pegawai = SimpegPegawai::find($id);

        if (!$pegawai) {
            return response()->json(['success' => false, 'message' => 'Pegawai tidak ditemukan'], 404);
        }

        $riwayat = $pegawai->dataHubunganKerja()->with('hubunganKerja')
            ->orderBy('tanggal_mulai', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
                'riwayat' => $riwayat
            ]
        ]);
    }

  public function rekapKehadiran($id)
{
    // Gunakan findOrFail untuk otomatis 404 jika pegawai target tidak ada
    $pegawai = SimpegPegawai::findOrFail($id);

    // Ambil pengguna yang sedang login
    $authenticatedUser = Auth::user();

    // Periksa apakah pengguna login
    if (!$authenticatedUser) {
        return response()->json(['success' => false, 'message' => 'Tidak ada pengguna yang terotentikasi.'], 401);
    }

    // Ambil relasi pegawai dari pengguna yang login
    $pegawaiYangLogin = $authenticatedUser->pegawai;

    // PERBAIKAN: Periksa apakah pengguna yang login memiliki data pegawai
    if (!$pegawaiYangLogin) {
        return response()->json(['success' => false, 'message' => 'Data pegawai untuk pengguna yang login tidak ditemukan.'], 403);
    }

    // Otorisasi sekarang aman karena $pegawaiYangLogin sudah dipastikan tidak null
    if (!$pegawaiYangLogin->hasRole('Admin') && $pegawaiYangLogin->id !== $pegawai->id) {
        return response()->json(['success' => false, 'message' => 'Akses ditolak. Anda hanya dapat melihat rekap kehadiran Anda sendiri.'], 403);
    }

    // Ganti 'created_at' dengan nama kolom tanggal Anda yang sebenarnya di tabel simpeg_absensi_record
    $namaKolomTanggal = 'created_at';

    $rekap = $pegawai->absensiRecord()
        ->select(
            DB::raw("EXTRACT(YEAR FROM $namaKolomTanggal) as tahun"),
            DB::raw("EXTRACT(MONTH FROM $namaKolomTanggal) as bulan"),
            DB::raw("COUNT(*) as total_kehadiran"),
            DB::raw("SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) as hadir"),
            DB::raw("SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) as sakit"),
            DB::raw("SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) as izin"),
            DB::raw("SUM(CASE WHEN status = 'cuti' THEN 1 ELSE 0 END) as cuti"),
            DB::raw("SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) as alpa")
        )
        ->groupBy('tahun', 'bulan')
        ->orderBy('tahun', 'desc')
        ->orderBy('bulan', 'desc')
        ->paginate(12);

    return response()->json([
        'success' => true,
        'data' => [
            'pegawai' => $pegawai->only(['id', 'nip', 'nama']),
            'rekap' => $rekap
        ]
    ]);
}
}