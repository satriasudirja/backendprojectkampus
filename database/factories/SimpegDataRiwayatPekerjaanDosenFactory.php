<?php

namespace Database\Factories;

use App\Models\SimpegDataRiwayatPekerjaanDosen;
use App\Models\SimpegPegawai;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SimpegDataRiwayatPekerjaanDosen>
 */
class SimpegDataRiwayatPekerjaanDosenFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SimpegDataRiwayatPekerjaanDosen::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Get a random pegawai ID or create one if none exists
        $pegawaiId = SimpegPegawai::inRandomOrder()->first()?->id ??
            SimpegPegawai::factory()->create()->id;

        // Generate random start date between 5-15 years ago
        $mulaiBekerja = Carbon::now()->subYears(rand(5, 15))->subMonths(rand(0, 11));
        
        // Determine if employment has ended (70% chance it has ended)
        $hasEnded = rand(1, 10) <= 7;
        
        // If it has ended, set end date between start date and now
        $selesaiBekerja = $hasEnded 
            ? Carbon::parse($mulaiBekerja)->addYears(rand(1, 5))->addMonths(rand(0, 11))
            : null;

        // Make sure end date is not in the future
        if ($selesaiBekerja && $selesaiBekerja->gt(Carbon::now())) {
            $selesaiBekerja = Carbon::now()->subMonths(rand(1, 6));
        }

        // Status options with weights (draft more common for testing)
        $statusOptions = [
            'draft' => 50,
            'diajukan' => 20, 
            'disetujui' => 20,
            'ditolak' => 10,
        ];

        // Pick a status based on weights
        $status = $this->pickWeighted($statusOptions);

        // Typical bidang usaha options for dosen
        $bidangUsahaOptions = [
            'Pendidikan', 'Pendidikan Tinggi', 'Penelitian', 'Riset & Pengembangan',
            'Akademik', 'Pelatihan', 'Konsultan Pendidikan', 'Pengembangan SDM',
            'Pendidikan Vokasi', 'Pendidikan Profesional', 'Lembaga Pendidikan'
        ];

        // Typical job types for dosen
        $jenisPekerjaanOptions = [
            'Dosen', 'Dosen Tetap', 'Dosen Tidak Tetap', 'Dosen Tamu',
            'Peneliti', 'Asisten Dosen', 'Pengajar', 'Tutor',
            'Instruktur', 'Fasilitator', 'Narasumber', 'Pembimbing Akademik',
            'Profesor', 'Lektor', 'Tenaga Pengajar', 'Konsultan Akademik'
        ];

        // Select a random bidang usaha
        $bidangUsaha = $this->faker->randomElement($bidangUsahaOptions);
        
        // Select a job type
        $jenisPekerjaan = $this->faker->randomElement($jenisPekerjaanOptions);

        return [
            'pegawai_id' => $pegawaiId,
            'bidang_usaha' => $bidangUsaha,
            'jenis_pekerjaan' => $jenisPekerjaan,
            'jabatan' => $this->generateJobTitle($jenisPekerjaan),
            'instansi' => $this->generateEducationalInstitution(),
            'divisi' => $this->faker->randomElement([
                'Fakultas Teknik', 'Fakultas Ekonomi', 'Fakultas Hukum', 
                'Fakultas Kedokteran', 'Fakultas MIPA', 'Fakultas Ilmu Sosial',
                'Fakultas Humaniora', 'Fakultas Ilmu Komputer', 'Fakultas Pendidikan',
                'Program Pascasarjana', 'Departemen Akademik', 'Jurusan Informatika',
                'Jurusan Manajemen', 'Jurusan Akuntansi', null
            ]),
            'deskripsi_kerja' => $this->generateJobDescription($jenisPekerjaan),
            'mulai_bekerja' => $mulaiBekerja->toDateString(),
            'selesai_bekerja' => $selesaiBekerja ? $selesaiBekerja->toDateString() : null,
            'area_pekerjaan' => $this->faker->randomElement(['Dalam Negeri', 'Luar Negeri']),
            'status_pengajuan' => $status,
            'tgl_input' => Carbon::now()->toDateString(),
            'tgl_diajukan' => in_array($status, ['diajukan', 'disetujui', 'ditolak']) ? Carbon::now()->subDays(rand(1, 30))->toDateString() : null,
            'tgl_disetujui' => $status === 'disetujui' ? Carbon::now()->subDays(rand(1, 15))->toDateString() : null,
            'tgl_ditolak' => $status === 'ditolak' ? Carbon::now()->subDays(rand(1, 15))->toDateString() : null,
            'keterangan' => $status === 'ditolak' ? $this->faker->sentence() : null,
        ];
    }

    /**
     * Generate a job title based on the job type.
     *
     * @param string $jenisPekerjaan
     * @return string
     */
    private function generateJobTitle(string $jenisPekerjaan): string
    {
        $prefixes = ['Junior', 'Senior', 'Lead', 'Head of', 'Assistant', 'Chief', ''];
        $suffixes = ['', 'Specialist', 'Officer', 'Coordinator', 'Supervisor', 'Manager'];
        
        // For specific job types, use more appropriate titles
        $specificTitles = [
            'Dosen' => ['Dosen', 'Dosen Tetap', 'Dosen Tidak Tetap', 'Asisten Dosen', 'Dosen Senior', 'Profesor'],
            'Dosen Tetap' => ['Dosen Tetap', 'Dosen Senior', 'Profesor', 'Lektor', 'Lektor Kepala', 'Asisten Ahli'],
            'Dosen Tidak Tetap' => ['Dosen Tidak Tetap', 'Dosen Luar Biasa', 'Dosen Kontrak', 'Pengajar Tamu'],
            'Peneliti' => ['Peneliti', 'Peneliti Senior', 'Asisten Peneliti', 'Kepala Peneliti', 'Koordinator Penelitian'],
            'Profesor' => ['Profesor', 'Guru Besar', 'Profesor Riset', 'Profesor Tamu', 'Distinguished Professor'],
            'Lektor' => ['Lektor', 'Lektor Kepala', 'Asisten Ahli', 'Tenaga Pengajar'],
        ];
        
        if (array_key_exists($jenisPekerjaan, $specificTitles)) {
            return $this->faker->randomElement($specificTitles[$jenisPekerjaan]);
        }
        
        // For general job types, combine prefix, job type, and suffix
        $prefix = $this->faker->randomElement($prefixes);
        $suffix = $this->faker->randomElement($suffixes);
        
        $title = trim($prefix . ' ' . $jenisPekerjaan . ' ' . $suffix);
        
        return $title;
    }

    /**
     * Generate an educational institution name.
     *
     * @return string
     */
    private function generateEducationalInstitution(): string
    {
        $types = [
            'Universitas', 'Institut', 'Sekolah Tinggi', 'Politeknik', 
            'Akademi', 'Perguruan Tinggi', 'Kolese', 'STMIK', 'STIE'
        ];

        $names = [
            'Indonesia', 'Nusantara', 'Bangsa', 'Negeri', 'Nasional',
            'Teknologi', 'Pendidikan', 'Komputer', 'Informatika', 'Ekonomi',
            'Bisnis', 'Sains', 'Ilmu Pengetahuan', 'Teknik', 'Manajemen',
            'Swadaya', 'Mandiri', 'Merdeka', 'Telkom', 'Multimedia',
            'Digital', 'Global', 'Internasional', 'Terbuka'
        ];

        $locations = [
            'Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang', 
            'Malang', 'Medan', 'Makassar', 'Bali', 'Palembang',
            'Indonesia', 'Jawa Barat', 'Jawa Timur', 'Jawa Tengah', 'Sumatra'
        ];

        $type = $this->faker->randomElement($types);
        $name = $this->faker->randomElement($names);
        $location = $this->faker->randomElement($locations);

        // 30% chance to include the location
        $includeLocation = rand(1, 10) <= 3;
        
        return $includeLocation ? "{$type} {$name} {$location}" : "{$type} {$name}";
    }

    /**
     * Generate a job description based on the job type.
     *
     * @param string $jenisPekerjaan
     * @return string
     */
    private function generateJobDescription(string $jenisPekerjaan): string
    {
        $descriptions = [
            'Dosen' => [
                'Mengajar mata kuliah {subject} di tingkat {level}.',
                'Membimbing mahasiswa dalam penulisan tugas akhir dan skripsi.',
                'Melakukan penelitian dalam bidang {subject} dan mempublikasikan hasil penelitian.',
                'Mengelola program perkuliahan dan mengembangkan silabus mata kuliah {subject}.',
                'Berpartisipasi dalam kegiatan akademik dan pengembangan kurikulum.'
            ],
            'Peneliti' => [
                'Melakukan penelitian di bidang {subject} dengan fokus pada {focus}.',
                'Menulis dan mempublikasikan makalah ilmiah di jurnal nasional dan internasional.',
                'Mengembangkan metodologi penelitian dan analisis data untuk proyek {project}.',
                'Berkolaborasi dengan peneliti lain dalam mengembangkan proposal penelitian.',
                'Membimbing mahasiswa dan asisten peneliti dalam kegiatan riset.'
            ],
            'Asisten Dosen' => [
                'Membantu dosen dalam menyiapkan materi perkuliahan dan praktikum.',
                'Mengawasi sesi praktikum dan latihan untuk mata kuliah {subject}.',
                'Mengoreksi tugas dan ujian mahasiswa di bawah pengawasan dosen.',
                'Menyediakan bantuan tambahan bagi mahasiswa di luar jam perkuliahan.',
                'Membantu dalam pengembangan materi pembelajaran dan media pendukung.'
            ],
            'default' => [
                'Mengajar mata kuliah {subject} dengan metode pembelajaran aktif dan inovatif.',
                'Melakukan penelitian akademik dan publikasi ilmiah dalam bidang keahlian.',
                'Berpartisipasi dalam pengembangan kurikulum dan program akademik.',
                'Membimbing mahasiswa dalam penelitian dan penulisan karya ilmiah.',
                'Terlibat dalam kegiatan pengabdian masyarakat dan kolaborasi antar institusi pendidikan.'
            ]
        ];
        
        $subjects = [
            'Pemrograman', 'Basis Data', 'Kecerdasan Buatan', 'Jaringan Komputer',
            'Akuntansi', 'Manajemen', 'Ekonomi', 'Hukum', 'Kedokteran',
            'Biologi', 'Fisika', 'Kimia', 'Matematika', 'Statistika',
            'Bahasa Inggris', 'Komunikasi', 'Psikologi', 'Sosiologi', 'Sejarah'
        ];
        
        $levels = ['sarjana', 'pascasarjana', 'doktoral', 'diploma', 'vokasi'];
        
        $focuses = [
            'pengembangan teknologi', 'inovasi pendidikan', 'kebijakan publik',
            'analisis data', 'sistem informasi', 'keberlanjutan', 'energi terbarukan',
            'kesehatan masyarakat', 'teknologi pendidikan', 'ekonomi digital'
        ];
        
        $projects = [
            'pengembangan aplikasi', 'analisis big data', 'sistem cerdas',
            'pembelajaran mesin', 'inovasi kurikulum', 'pengembangan energi terbarukan',
            'kebijakan pendidikan tinggi', 'transformasi digital pendidikan'
        ];

        // Get description templates based on job type or use default
        $descTemplates = $descriptions[$jenisPekerjaan] ?? $descriptions['default'];
        
        // Select 2-3 random description templates
        $selectedTemplates = $this->faker->randomElements($descTemplates, rand(2, 3));
        
        // Replace placeholders with random values
        $processedDescriptions = array_map(function($template) use ($subjects, $levels, $focuses, $projects) {
            $processed = str_replace('{subject}', $this->faker->randomElement($subjects), $template);
            $processed = str_replace('{level}', $this->faker->randomElement($levels), $processed);
            $processed = str_replace('{focus}', $this->faker->randomElement($focuses), $processed);
            $processed = str_replace('{project}', $this->faker->randomElement($projects), $processed);
            return $processed;
        }, $selectedTemplates);
        
        return implode(' ', $processedDescriptions);
    }

    /**
     * Pick a random value based on weighted options.
     *
     * @param array $options Array of options with weights (e.g. ['option1' => 70, 'option2' => 30])
     * @return string Selected option
     */
    private function pickWeighted(array $options): string
    {
        $total = array_sum($options);
        $rand = mt_rand(1, $total);
        
        $runningTotal = 0;
        foreach ($options as $option => $weight) {
            $runningTotal += $weight;
            if ($rand <= $runningTotal) {
                return $option;
            }
        }
        
        // Fallback to first option (shouldn't reach here)
        reset($options);
        return key($options);
    }

    /**
     * State for draft status.
     *
     * @return $this
     */
    public function draft(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status_pengajuan' => 'draft',
                'tgl_diajukan' => null,
                'tgl_disetujui' => null,
                'tgl_ditolak' => null,
            ];
        });
    }

    /**
     * State for submitted status.
     *
     * @return $this
     */
    public function diajukan(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'status_pengajuan' => 'diajukan',
                'tgl_diajukan' => Carbon::now()->subDays(rand(1, 10))->toDateString(),
                'tgl_disetujui' => null,
                'tgl_ditolak' => null,
            ];
        });
    }

    /**
     * State for approved status.
     *
     * @return $this
     */
    public function disetujui(): self
    {
        return $this->state(function (array $attributes) {
            $tglDiajukan = Carbon::now()->subDays(rand(11, 20))->toDateString();
            
            return [
                'status_pengajuan' => 'disetujui',
                'tgl_diajukan' => $tglDiajukan,
                'tgl_disetujui' => Carbon::now()->subDays(rand(1, 10))->toDateString(),
                'tgl_ditolak' => null,
            ];
        });
    }

    /**
     * State for rejected status.
     *
     * @return $this
     */
    public function ditolak(): self
    {
        return $this->state(function (array $attributes) {
            $tglDiajukan = Carbon::now()->subDays(rand(11, 20))->toDateString();
            
            return [
                'status_pengajuan' => 'ditolak',
                'tgl_diajukan' => $tglDiajukan,
                'tgl_disetujui' => null,
                'tgl_ditolak' => Carbon::now()->subDays(rand(1, 10))->toDateString(),
                'keterangan' => $this->faker->randomElement([
                    'Data tidak lengkap',
                    'Dokumen pendukung tidak valid',
                    'Tanggal tidak sesuai',
                    'Informasi tidak akurat',
                    'Perlu revisi pada bagian deskripsi kerja',
                    'Instansi tidak terdaftar dalam database kami'
                ]),
            ];
        });
    }

    /**
     * State for setting a specific pegawai ID.
     *
     * @param int $pegawaiId
     * @return $this
     */
    public function forPegawai(int $pegawaiId): self
    {
        return $this->state(function (array $attributes) use ($pegawaiId) {
            return [
                'pegawai_id' => $pegawaiId,
            ];
        });
    }

    /**
     * State for domestic work area.
     *
     * @return $this
     */
    public function dalamNegeri(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'area_pekerjaan' => 'Dalam Negeri',
            ];
        });
    }

    /**
     * State for international work area.
     *
     * @return $this
     */
    public function luarNegeri(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'area_pekerjaan' => 'Luar Negeri',
            ];
        });
    }

    /**
     * State for active employment (no end date).
     *
     * @return $this
     */
    public function masihAktif(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'selesai_bekerja' => null,
            ];
        });
    }

    /**
     * State for creating with supporting documents.
     *
     * @param int $count Number of documents to create
     * @return $this
     */
    public function withDocuments(int $count = 1): self
    {
        return $this->afterCreating(function (SimpegDataRiwayatPekerjaanDosen $riwayatPekerjaanDosen) use ($count) {
            for ($i = 0; $i < $count; $i++) {
                $riwayatPekerjaanDosen->dokumenPendukung()->create([
                    'tipe_dokumen' => 'pekerjaan_dosen',
                    'file_path' => 'sample_' . uniqid() . '.pdf',
                    'nama_dokumen' => $this->faker->randomElement([
                        'Surat Kontrak',
                        'Surat Keterangan',
                        'Sertifikat',
                        'Surat Rekomendasi',
                        'Surat Pengalaman Kerja',
                        'Dokumen Pendukung'
                    ]) . ' ' . ($i + 1),
                    'jenis_dokumen_id' => rand(1, 5),
                    'keterangan' => $this->faker->sentence(),
                    'pendukungable_type' => SimpegDataRiwayatPekerjaanDosen::class,
                    'pendukungable_id' => $riwayatPekerjaanDosen->id
                ]);
            }
        });
    }
}