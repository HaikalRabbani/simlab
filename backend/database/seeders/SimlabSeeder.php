<?php

namespace Database\Seeders;

use App\Models\Examination;
use App\Models\ExaminationResult;
use App\Models\School;
use App\Models\ScreeningSchedule;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh untuk pengembangan & demo.
 *
 * Catatan asumsi (spec Bagian 14.3): pembagian kab/kota ke dalam wilayah_group
 * belum ada daftar resmi dari Dinas — mapping di bawah adalah asumsi wajar dan
 * mudah disesuaikan lewat konstanta WILAYAH_GROUP.
 */
class SimlabSeeder extends Seeder
{
    public const WILAYAH_GROUP = [
        'Bandung Raya' => ['Kota Bandung', 'Kab. Bandung', 'Kab. Bandung Barat', 'Kota Cimahi', 'Kab. Sumedang'],
        'Bodebek' => ['Kab. Bogor', 'Kota Bogor', 'Kota Depok', 'Kab. Bekasi', 'Kota Bekasi'],
        'Pantura' => ['Kab. Karawang', 'Kab. Purwakarta', 'Kab. Subang', 'Kab. Indramayu', 'Kab. Cirebon', 'Kota Cirebon'],
        'Priangan Timur' => ['Kab. Sukabumi', 'Kota Sukabumi', 'Kab. Cianjur', 'Kab. Garut', 'Kab. Tasikmalaya', 'Kota Tasikmalaya', 'Kab. Ciamis', 'Kota Banjar', 'Kab. Pangandaran', 'Kab. Kuningan', 'Kab. Majalengka'],
    ];

    private const KECAMATAN = ['Coblong', 'Bojongsoang', 'Cicendo', 'Andir', 'Dayeuhkolot', 'Margahayu', 'Cibeunying', 'Antapani', 'Buahbatu', 'Rancasari'];

    private const NAMA_LAKI = ['Ahmad', 'Rizky', 'Dedi', 'Fajar', 'Budi', 'Andi', 'Rahmat', 'Ilham', 'Yusuf', 'Dimas', 'Agus', 'Rian', 'Bayu', 'Eko', 'Hendra', 'Gilang', 'Raka', 'Taufik', 'Rudi', 'Arif'];
    private const NAMA_PEREMPUAN = ['Siti', 'Nur', 'Rina', 'Dewi', 'Ayu', 'Fitri', 'Laila', 'Intan', 'Putri', 'Maya', 'Ratna', 'Sri', 'Wulan', 'Citra', 'Nadia', 'Salsa', 'Zahra', 'Amelia', 'Friska', 'Indah'];
    private const NAMA_BELAKANG = ['Ramadhan', 'Hidayat', 'Nugraha', 'Pratama', 'Kusuma', 'Saputra', 'Wijaya', 'Setiawan', 'Gunawan', 'Firmansyah', 'Maulana', 'Santoso', 'Utami', 'Lestari', 'Anggraini', 'Puspita', 'Rahayu', 'Handayani', 'Sari', 'Mulyani'];

    private const KELAS_SMA = ['X MIPA 1', 'X MIPA 2', 'X IPS 1', 'XI MIPA 1', 'XI IPS 1', 'XII MIPA 1', 'XII MIPA 2', 'XII IPS 1'];
    private const KELAS_SMK = ['X TKJ 1', 'X RPL 1', 'XI TKJ 1', 'XI RPL 1', 'XI Akuntansi 1', 'XII TKJ 1', 'XII RPL 1', 'XII Akuntansi 1'];
    private const KELAS_MA = ['X IPA 1', 'X IPS 1', 'XI IPA 1', 'XI IPS 1', 'XII IPA 1', 'XII IPS 1'];

    private array $nisnTerpakai = [];

    public function run(): void
    {
        mt_srand(20260904);

        if (School::exists()) {
            $this->command?->warn('Data sekolah sudah ada — SimlabSeeder dilewati. Reset dulu dengan migrate:fresh bila ingin seed ulang.');

            return;
        }

        $this->buatUserDemo();

        $siswaTotal = 0;
        $examTotal = 0;

        foreach (self::WILAYAH_GROUP as $group => $kabList) {
            foreach ($kabList as $kab) {
                $petugas = $this->buatPetugas($kab);
                $this->buatPengawas($kab);

                foreach (['SMA', 'SMK', 'MA'] as $jenjang) {
                    $school = $this->buatSekolah($kab, $group, $jenjang);
                    $siswa = $this->buatSiswa($school);
                    $siswaTotal += count($siswa);

                    $examTotal += $this->simulasiKunjungan($petugas, $school, $siswa);
                }
            }
        }

        $this->command?->info("Selesai: 81 sekolah, {$siswaTotal} siswa, {$examTotal} pemeriksaan tersimulasi.");

        // Pastikan tiap petugas punya jadwal HARI INI agar wizard Input Pemeriksaan bisa dites.
        $this->call(JadwalHariIniSeeder::class);

        // Data demo untuk Panel Pengawas (koreksi) & Stok Alat Uji.
        $this->call(DemoKoreksiStokSeeder::class);
    }

    private function buatUserDemo(): void
    {
        User::firstOrCreate(
            ['email' => 'dinas@simlab.test'],
            ['nama' => 'Drs. Hendra Gunawan', 'password' => 'password', 'role' => User::ROLE_DINAS, 'wilayah_scope' => null]
        );
    }

    private function buatPetugas(string $kab): User
    {
        $nama = (mt_rand(0, 1) === 0 ? self::NAMA_LAKI : self::NAMA_PEREMPUAN)[mt_rand(0, 19)]
            .' '.self::NAMA_BELAKANG[mt_rand(0, 19)];
        $email = 'petugas.'.strtolower(str_replace(['.', ' '], ['', '-'], $kab)).'@simlab.test';

        return User::firstOrCreate(
            ['email' => $email],
            ['nama' => $nama, 'password' => 'password', 'role' => User::ROLE_PETUGAS, 'wilayah_scope' => $kab]
        );
    }

    private function buatPengawas(string $kab): User
    {
        $nama = (mt_rand(0, 1) === 0 ? self::NAMA_LAKI : self::NAMA_PEREMPUAN)[mt_rand(0, 19)]
            .' '.self::NAMA_BELAKANG[mt_rand(0, 19)];
        $email = 'pengawas.'.strtolower(str_replace(['.', ' '], ['', '-'], $kab)).'@simlab.test';

        return User::firstOrCreate(
            ['email' => $email],
            ['nama' => $nama, 'password' => 'password', 'role' => User::ROLE_PENGAWAS, 'wilayah_scope' => $kab]
        );
    }

    private function buatSekolah(string $kab, string $group, string $jenjang): School
    {
        $npsn = (string) mt_rand(20000000, 69999999);

        return School::create([
            'npsn' => $npsn,
            'nama' => $this->namaSekolah($kab, $jenjang),
            'kab_kota' => $kab,
            'kecamatan' => self::KECAMATAN[mt_rand(0, count(self::KECAMATAN) - 1)],
            'wilayah_group' => $group,
            'jenjang' => $jenjang,
            'target_siswa' => mt_rand(180, 420),
        ]);
    }

    private function namaSekolah(string $kab, string $jenjang): string
    {
        $kota = str_contains($kab, 'Kota') ? '' : 'Negeri ';
        $angka = mt_rand(1, 25);

        return match ($jenjang) {
            'SMA' => "SMA {$kota}{$angka} ".preg_replace('/^(Kab\.|Kota) /', '', $kab),
            'SMK' => "SMK {$kota}{$angka} ".preg_replace('/^(Kab\.|Kota) /', '', $kab),
            default => "MA {$kota}{$angka} ".preg_replace('/^(Kab\.|Kota) /', '', $kab),
        };
    }

    /**
     * @return array<int, Student>
     */
    private function buatSiswa(School $school): array
    {
        $kelasPool = match ($school->jenjang) {
            'SMA' => self::KELAS_SMA,
            'SMK' => self::KELAS_SMK,
            default => self::KELAS_MA,
        };

        $rows = [];
        $students = [];
        for ($i = 0; $i < 30; $i++) {
            $jenisKelamin = mt_rand(0, 1) === 0 ? 'L' : 'P';
            $namaDepan = $jenisKelamin === 'L' ? self::NAMA_LAKI : self::NAMA_PEREMPUAN;
            $nama = $namaDepan[mt_rand(0, 19)].' '.self::NAMA_BELAKANG[mt_rand(0, 19)];
            $kelas = $kelasPool[mt_rand(0, count($kelasPool) - 1)];
            $nisn = $this->nisnUnik();

            $rows[] = [
                'nisn' => $nisn,
                'nama' => $nama,
                'kelas' => $kelas,
                'jenis_kelamin' => $jenisKelamin,
                'tanggal_lahir' => Carbon::create(mt_rand(2006, 2010), mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
                'school_id' => $school->id,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $students[] = ['nisn' => $nisn, 'nama' => $nama, 'kelas' => $kelas, 'jenis_kelamin' => $jenisKelamin];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('students')->insert($chunk);
        }

        // Ambil kembali id siswa yang baru dibuat untuk dipakai simulasi
        $created = Student::where('school_id', $school->id)->pluck('id', 'nisn');
        foreach ($students as $i => $student) {
            $students[$i]['id'] = $created[$student['nisn']];
        }

        return $students;
    }

    private function simulasiKunjungan(User $petugas, School $school, array $siswa): int
    {
        $jumlahDiperiksa = mt_rand(12, min(28, count($siswa)));
        $subset = $siswa;
        shuffle($subset);
        $subset = array_slice($subset, 0, $jumlahDiperiksa);

        $tanggalKunjungan = Carbon::today()->subDays(mt_rand(1, 75));
        $sesi = mt_rand(0, 1) === 0 ? 'pagi' : 'siang';

        $schedule = ScreeningSchedule::create([
            'school_id' => $school->id,
            'petugas_id' => $petugas->id,
            'tanggal' => $tanggalKunjungan->toDateString(),
            'sesi' => $sesi,
            'target_siswa' => count($siswa),
            'status' => 'selesai',
        ]);

        $examCount = 0;
        $results = [];
        foreach ($subset as $student) {
            $reaktifParams = [];
            // Peluang reaktif ~1.2% agar proporsi realistis
            if (mt_rand(1, 1000) <= 12) {
                $reaktifParams[] = Examination::PARAMETERS[mt_rand(0, 6)];
                if (mt_rand(1, 100) <= 8) {
                    $reaktifParams[] = Examination::PARAMETERS[mt_rand(0, 6)];
                }
                $reaktifParams = array_values(array_unique($reaktifParams));
            }

            $waktuInput = Carbon::parse($tanggalKunjungan->toDateString().' '.($sesi === 'pagi' ? '07:30' : '12:30'))
                ->addMinutes($examCount * 3);

            $examId = DB::table('examinations')->insertGetId([
                'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
                'schedule_id' => $schedule->id,
                'student_id' => $student['id'],
                'petugas_id' => $petugas->id,
                'waktu_input' => $waktuInput,
                'pendamping' => 'Guru BK — '.self::NAMA_BELAKANG[mt_rand(0, 19)],
                'rencana_tindak_lanjut' => $reaktifParams === [] ? null : 'rujuk_uji_konfirmasi',
                'sampel_disegel' => $reaktifParams === [] ? null : true,
                'kode_segel' => $reaktifParams === [] ? null : 'SG-'.mt_rand(1000, 9999),
                'status_kirim' => 'terkirim',
                'is_locked' => true,
                'strip_lot_code' => 'RT-'.mt_rand(1000, 9999).'-'.mt_rand(1000, 9999),
                'strip_expiry_date' => Carbon::today()->addMonths(mt_rand(3, 12))->toDateString(),
                'created_at' => $waktuInput,
                'updated_at' => $waktuInput,
            ]);
            $examCount++;

            foreach (Examination::PARAMETERS as $parameter) {
                $results[] = [
                    'examination_id' => $examId,
                    'parameter' => $parameter,
                    'hasil' => in_array($parameter, $reaktifParams, true) ? 'positif' : 'negatif',
                    'created_at' => $waktuInput,
                    'updated_at' => $waktuInput,
                ];
            }
        }

        foreach (array_chunk($results, 500) as $chunk) {
            DB::table('examination_results')->insert($chunk);
        }

        return $examCount;
    }

    private function nisnUnik(): string
    {
        do {
            $nisn = (string) mt_rand(1000000000, 9999999999);
        } while (isset($this->nisnTerpakai[$nisn]));
        $this->nisnTerpakai[$nisn] = true;

        return $nisn;
    }
}
