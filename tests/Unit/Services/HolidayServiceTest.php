<?php

namespace Tests\Unit\Services;

use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HolidayServiceTest extends TestCase
{
    /**
     * Uji apakah service dapat mengambil data hari libur dengan benar dari API.
     *
     * @return void
     */
    public function test_getHolidays_fetches_data_successfully(): void
    {
        // 1. Siapkan data palsu (fake) seolah-olah dari API
        $fakeHolidays = [
            ['date' => '2025-08-17', 'name' => 'Hari Kemerdekaan'],
        ];

        // 2. Atur 'Http' palsu untuk mengembalikan data di atas saat URL dipanggil
        Http::fake([
            'libur.deno.dev/api?year=2025' => Http::response($fakeHolidays, 200),
        ]);

        // 3. Panggil method yang ingin diuji
        $service = new HolidayService();
        $holidays = $service->getHolidays(2025);

        // 4. Pastikan hasilnya sesuai dengan yang diharapkan
        $this->assertIsArray($holidays);
        $this->assertContains('2025-08-17', $holidays);
    }

    /**
     * Uji apakah method isHoliday() dapat mengidentifikasi hari libur dan hari kerja.
     *
     * @return void
     */
    public function test_isHoliday_identifies_weekends_and_national_holidays(): void
    {
        $service = new HolidayService();

        // Siapkan 'Http' palsu dengan data libur nasional
        Http::fake([
            'libur.deno.dev/api?year=2025' => Http::response([
                ['date' => '2025-08-17', 'name' => 'Hari Kemerdekaan'], // Sabtu (tapi libur nasional)
            ], 200),
        ]);

        // Uji hari kerja biasa (Senin, 30 Juni 2025) -> harusnya false
        $this->assertFalse($service->isHoliday(Carbon::create(2025, 6, 30)));

        // Uji hari libur nasional -> harusnya true
        $this->assertTrue($service->isHoliday(Carbon::create(2025, 8, 17)));

        // Uji hari Minggu (6 Juli 2025) -> harusnya true
        $this->assertTrue($service->isHoliday(Carbon::create(2025, 7, 6)));
    }

    /**
     * Uji apakah method calculateWorkingDays() menghitung dengan benar.
     *
     * @return void
     */
    public function test_calculateWorkingDays_is_correct(): void
    {
        $service = new HolidayService();

        // Siapkan 'Http' palsu dengan 1 hari libur nasional
        Http::fake([
            'libur.deno.dev/api?year=2025' => Http::response([
                ['date' => '2025-07-02', 'name' => 'Libur Palsu'], // Rabu
            ], 200),
        ]);

        // Rentang 1-7 Juli 2025 (7 hari)
        // Libur: 2 Juli (Rabu, libur nasional palsu) & 6 Juli (Minggu)
        // Total hari kerja harusnya 7 - 2 = 5 hari.
        $workingDays = $service->calculateWorkingDays('2025-07-01', '2025-07-07');

        $this->assertEquals(5, $workingDays);
    }
}
