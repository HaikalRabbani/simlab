<?php

namespace Tests\Feature;

use App\Models\Examination;
use App\Models\ExaminationResult;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAggregatesTest extends TestCase
{
    use RefreshDatabase;

    private function buatSekolah(string $nama, string $kab, string $group, string $jenjang): School
    {
        return School::create([
            'npsn' => (string) random_int(10000000, 99999999),
            'nama' => $nama,
            'kab_kota' => $kab,
            'kecamatan' => 'Kec. Contoh',
            'wilayah_group' => $group,
            'jenjang' => $jenjang,
            'target_siswa' => 180,
        ]);
    }

    private function buatSiswa(School $school, string $nama, string $jk, string $kelas): Student
    {
        return Student::create([
            'nisn' => (string) random_int(1000000000, 9999999999),
            'nama' => $nama,
            'kelas' => $kelas,
            'jenis_kelamin' => $jk,
            'tanggal_lahir' => '2008-01-01',
            'school_id' => $school->id,
        ]);
    }

    private function buatPemeriksaan(User $petugas, Student $siswa, array $reaktif = [], ?string $rencana = 'rujuk_uji_konfirmasi'): Examination
    {
        $examination = Examination::create([
            'student_id' => $siswa->id,
            'petugas_id' => $petugas->id,
            'waktu_input' => now(),
            'pendamping' => 'Guru BK',
            'rencana_tindak_lanjut' => $reaktif === [] ? null : $rencana,
            'sampel_disegel' => $reaktif === [] ? null : true,
            'kode_segel' => $reaktif === [] ? null : 'SG-777',
            'status_kirim' => 'terkirim',
            'is_locked' => true,
            'strip_lot_code' => 'RT-2609-A',
            'strip_expiry_date' => now()->addMonths(6)->toDateString(),
        ]);

        foreach (Examination::PARAMETERS as $parameter) {
            ExaminationResult::create([
                'examination_id' => $examination->id,
                'parameter' => $parameter,
                'hasil' => in_array($parameter, $reaktif, true) ? 'positif' : 'negatif',
            ]);
        }

        return $examination;
    }

    private function setUpData(): array
    {
        $dinas = User::factory()->dinas()->create();
        $petugas = User::factory()->petugas('Kota Bandung')->create();

        $sma = $this->buatSekolah('SMA Negeri 1 Bandung', 'Kota Bandung', 'Bandung Raya', 'SMA');
        $smk = $this->buatSekolah('SMK Negeri 2 Bogor', 'Kabupaten Bogor', 'Bodebek', 'SMK');

        $siswa1 = $this->buatSiswa($sma, 'Andi', 'L', 'XI IPA 3');      // negatif
        $siswa2 = $this->buatSiswa($sma, 'Bunga', 'P', 'XII IPS 1');    // THC positif
        $siswa3 = $this->buatSiswa($smk, 'Cahyo', 'L', 'XI TKJ');       // MET + ALKOHOL positif

        $this->buatPemeriksaan($petugas, $siswa1);
        $this->buatPemeriksaan($petugas, $siswa2, ['THC']);
        $this->buatPemeriksaan($petugas, $siswa3, ['MET', 'ALKOHOL'], 'skrining_ulang');

        return [$dinas, $petugas];
    }

    public function test_summary_menghitung_angka_agregat(): void
    {
        [$dinas] = $this->setUpData();
        Sanctum::actingAs($dinas);

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('cards.siswa_diperiksa', 3)
            ->assertJsonPath('cards.sekolah_terjangkau', 2)
            ->assertJsonPath('cards.hasil_reaktif', 2)
            ->assertJsonPath('cards.sudah_uji_konfirmasi', 1)
            ->assertJsonPath('cards.sisa_menunggu_lab', 1)
            ->assertJsonPath('cards.petugas_aktif', 1)
            ->assertJsonPath('cards.kab_kota_petugas', 2);
    }

    public function test_reactive_by_region_mengelompokkan_per_kab_kota(): void
    {
        [$dinas] = $this->setUpData();
        Sanctum::actingAs($dinas);

        $response = $this->getJson('/api/dashboard/reactive-by-region')->assertOk();

        $wilayah = collect($response->json('wilayah'))->keyBy('kab_kota');
        $this->assertSame(2, $wilayah['Kota Bandung']['jumlah_diuji']);
        $this->assertSame(1, $wilayah['Kota Bandung']['jumlah_reaktif']);
        $this->assertSame(1, $wilayah['Kabupaten Bogor']['jumlah_diuji']);
        $this->assertSame(1, $wilayah['Kabupaten Bogor']['jumlah_reaktif']);
        $this->assertArrayHasKey('tingkat', $wilayah['Kota Bandung']);
    }

    public function test_reactive_by_region_selalu_menampilkan_27_kab_kota(): void
    {
        [$dinas] = $this->setUpData();
        Sanctum::actingAs($dinas);

        $response = $this->getJson('/api/dashboard/reactive-by-region')->assertOk();

        // Spec 6.3: heatmap harus memuat seluruh 27 kab/kota Jawa Barat,
        // termasuk wilayah tanpa pemeriksaan pada periode (angka 0).
        $wilayah = $response->json('wilayah');
        $this->assertGreaterThanOrEqual(count(\App\Support\DashboardData::KAB_KOTA_JABAR), count($wilayah));

        $byKab = collect($wilayah)->keyBy('kab_kota');
        $bogor = $byKab['Kab. Bogor'] ?? null;
        $this->assertNotNull($bogor);
        $this->assertArrayHasKey('jumlah_diuji', $bogor);
        $this->assertArrayHasKey('tingkat', $bogor);
        $this->assertSame('rendah', $bogor['tingkat']);
    }

    public function test_reactive_by_parameter_menghitung_per_parameter(): void
    {
        [$dinas] = $this->setUpData();
        Sanctum::actingAs($dinas);

        $response = $this->getJson('/api/dashboard/reactive-by-parameter')->assertOk();

        $this->assertSame(3, $response->json('total_hasil_reaktif')); // THC + MET + ALKOHOL
        $byParam = collect($response->json('parameters'))->keyBy('parameter');
        $this->assertSame(1, $byParam['THC']['jumlah_reaktif']);
        $this->assertSame(1, $byParam['MET']['jumlah_reaktif']);
        $this->assertSame(1, $byParam['ALKOHOL']['jumlah_reaktif']);
        $this->assertSame(0, $byParam['AMP']['jumlah_reaktif']);
    }

    public function test_top_schools_memuat_sekolah_dengan_temuan(): void
    {
        [$dinas] = $this->setUpData();
        Sanctum::actingAs($dinas);

        $response = $this->getJson('/api/dashboard/top-schools')->assertOk();

        $nama = collect($response->json('schools'))->pluck('nama');
        $this->assertTrue($nama->contains('SMK Negeri 2 Bogor'));
        $this->assertTrue($nama->contains('SMA Negeri 1 Bandung'));
    }

    public function test_by_gender_dan_filter_wilayah(): void
    {
        [$dinas] = $this->setUpData();
        Sanctum::actingAs($dinas);

        $all = $this->getJson('/api/demographics/by-gender')->assertOk();
        $this->assertSame(3, $all->json('total_diperiksa'));
        $data = collect($all->json('data'))->keyBy('jenis_kelamin');
        $this->assertSame(2, $data['L']['jumlah_diuji']);
        $this->assertSame(1, $data['L']['jumlah_reaktif']);
        $this->assertSame(1, $data['P']['jumlah_diuji']);
        $this->assertSame(1, $data['P']['jumlah_reaktif']);

        // Filter wilayah Bodebek -> hanya sekolah SMK di Kabupaten Bogor
        $bodebek = $this->getJson('/api/demographics/by-gender?wilayah=Bodebek')->assertOk();
        $this->assertSame(1, $bodebek->json('total_diperiksa'));
    }

    public function test_by_grade_menghitung_per_kelas_dan_jenjang(): void
    {
        [$dinas] = $this->setUpData();
        Sanctum::actingAs($dinas);

        $response = $this->getJson('/api/demographics/by-grade')->assertOk();

        $kelas = collect($response->json('data.kelas'))->keyBy('kategori');
        $this->assertSame(2, $kelas['XI']['jumlah_diuji']);
        $this->assertSame(1, $kelas['XI']['jumlah_reaktif']);
        $this->assertSame(1, $kelas['XII']['jumlah_diuji']);
        $this->assertSame(1, $kelas['XII']['jumlah_reaktif']);

        $jenjang = collect($response->json('data.jenjang'))->keyBy('kategori');
        $this->assertSame(2, $jenjang['SMA']['jumlah_diuji']);
        $this->assertSame(1, $jenjang['SMA']['jumlah_reaktif']);
        $this->assertSame(1, $jenjang['SMK']['jumlah_diuji']);
        $this->assertSame(1, $jenjang['SMK']['jumlah_reaktif']);
        $this->assertNotNull($response->json('insight'));
    }

    public function test_cross_table_menghitung_silang_parameter_dengan_dimensi(): void
    {
        [$dinas] = $this->setUpData();
        Sanctum::actingAs($dinas);

        $response = $this->getJson('/api/demographics/cross-table')->assertOk();

        $rows = collect($response->json('rows'))->keyBy('parameter');
        $thc = $rows['THC'];
        $this->assertSame(0, $thc['laki_laki']);
        $this->assertSame(1, $thc['perempuan']);
        $this->assertSame(1, $thc['sma']);
        $this->assertSame(0, $thc['smk']);
        $this->assertSame(1, $thc['kelas_xii']);
        $this->assertSame(1, $thc['total']);

        $met = $rows['MET'];
        $this->assertSame(1, $met['laki_laki']);
        $this->assertSame(1, $met['smk']);
        $this->assertSame(1, $met['kelas_xi']);
        $this->assertSame(1, $met['total']);
    }

    public function test_endpoint_agregat_hanya_untuk_dinas(): void
    {
        [$dinas, $petugas] = $this->setUpData();

        Sanctum::actingAs($petugas);
        $this->getJson('/api/dashboard/summary')->assertStatus(403);
        $this->getJson('/api/demographics/cross-table')->assertStatus(403);

        Sanctum::actingAs($dinas);
        $this->getJson('/api/dashboard/summary')->assertOk();
    }
}
