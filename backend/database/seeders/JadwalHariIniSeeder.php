<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\ScreeningSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Menambahkan jadwal kunjungan HARI INI untuk setiap petugas lapangan,
 * supaya wizard Input Pemeriksaan (spec 6.1) bisa langsung diuji
 * tanpa harus menunggu jadwal dari masa depan.
 *
 * Aman dijalankan berulang: dilewati bila petugas sudah punya jadwal hari ini.
 */
class JadwalHariIniSeeder extends Seeder
{
    public function run(): void
    {
        $petugasList = User::where('role', User::ROLE_PETUGAS)->get();

        $dibuat = 0;
        foreach ($petugasList as $petugas) {
            if (ScreeningSchedule::where('petugas_id', $petugas->id)
                ->whereDate('tanggal', today())
                ->exists()) {
                continue;
            }

            // Sekolah yang pernah dijadwalkan utk petugas ini; bila belum ada,
            // ambil sekolah pertama di wilayah tugasnya.
            $schoolIds = ScreeningSchedule::where('petugas_id', $petugas->id)
                ->distinct()
                ->pluck('school_id');

            if ($schoolIds->isEmpty()) {
                $school = School::where('kab_kota', $petugas->wilayah_scope)->first();
                if (! $school) {
                    continue;
                }
                $schoolIds = collect([$school->id]);
            }

            ScreeningSchedule::create([
                'school_id' => $schoolIds->random(),
                'petugas_id' => $petugas->id,
                'tanggal' => today()->toDateString(),
                'sesi' => 'pagi',
                'target_siswa' => 60,
                'status' => 'berlangsung',
            ]);
            $dibuat++;
        }

        $this->command?->info("Jadwal hari ini dibuat untuk {$dibuat} petugas.");
    }
}