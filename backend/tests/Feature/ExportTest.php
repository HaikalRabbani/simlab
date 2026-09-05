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

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private function setupData(): array
    {
        $dinas = User::factory()->dinas()->create();
        $petugas = User::factory()->petugas('Kota Bandung')->create();

        $school = School::create([
            'npsn' => '30000001',
            'nama' => 'SMA Negeri 3 Bandung',
            'kab_kota' => 'Kota Bandung',
            'kecamatan' => 'Coblong',
            'wilayah_group' => 'Bandung Raya',
            'jenjang' => 'SMA',
            'target_siswa' => 180,
        ]);

        $student = Student::create([
            'nisn' => '3000000001',
            'nama' => 'Siswa Ekspor',
            'kelas' => 'XI MIPA 1',
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '2008-03-10',
            'school_id' => $school->id,
        ]);

        $examination = Examination::create([
            'student_id' => $student->id,
            'petugas_id' => $petugas->id,
            'waktu_input' => now(),
            'pendamping' => 'Guru BK',
            'rencana_tindak_lanjut' => 'rujuk_uji_konfirmasi',
            'sampel_disegel' => true,
            'kode_segel' => 'SG-001',
            'status_kirim' => 'terkirim',
            'is_locked' => true,
            'strip_lot_code' => 'RT-1234-5678',
            'strip_expiry_date' => now()->addMonths(6)->toDateString(),
        ]);

        foreach (Examination::PARAMETERS as $parameter) {
            ExaminationResult::create([
                'examination_id' => $examination->id,
                'parameter' => $parameter,
                'hasil' => $parameter === 'THC' ? 'positif' : 'negatif',
            ]);
        }

        return [$dinas, $petugas];
    }

    public function test_export_schools_csv_untuk_dinas(): void
    {
        [$dinas] = $this->setupData();
        Sanctum::actingAs($dinas);

        $response = $this->get('/api/export/schools')->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));

        $content = $response->getContent();
        $this->assertStringContainsString('Nama Sekolah', $content);
        $this->assertStringContainsString('SMA Negeri 3 Bandung', $content);
        $this->assertStringContainsString('Pendampingan', $content); // badge tindak lanjut (100% reaktif)
    }

    public function test_export_regions_dan_parameters_csv(): void
    {
        [$dinas] = $this->setupData();
        Sanctum::actingAs($dinas);

        $regions = $this->get('/api/export/regions')->assertOk()->getContent();
        $this->assertStringContainsString('Kabupaten/Kota', $regions);
        $this->assertStringContainsString('Kota Bandung', $regions);

        $parameters = $this->get('/api/export/parameters')->assertOk()->getContent();
        $this->assertStringContainsString('Parameter', $parameters);
        $this->assertStringContainsString('THC (ganja)', $parameters);
    }

    public function test_export_ditolak_untuk_non_dinas_dan_tipe_tak_dikenal(): void
    {
        [$dinas, $petugas] = $this->setupData();

        Sanctum::actingAs($petugas);
        $this->get('/api/export/schools')->assertStatus(403);

        Sanctum::actingAs($dinas);
        $this->get('/api/export/tidak-ada')->assertStatus(404);
    }

    public function test_schools_status_mengembalikan_rekap_dengan_badge(): void
    {
        [$dinas] = $this->setupData();
        Sanctum::actingAs($dinas);

        $response = $this->getJson('/api/schools/status')->assertOk();

        $this->assertSame(1, $response->json('summary.total_sekolah'));
        $this->assertSame(1, $response->json('summary.sudah_diskrining'));
        $this->assertCount(1, $response->json('schools'));
        $this->assertSame('SMA Negeri 3 Bandung', $response->json('schools.0.nama'));
        $this->assertSame(1, $response->json('schools.0.jumlah_diuji'));
        $this->assertSame(1, $response->json('schools.0.jumlah_reaktif'));
        $this->assertEquals(100.0, $response->json('schools.0.persentase_reaktif'));
        $this->assertNotNull($response->json('schools.0.tindak_lanjut'));
        $this->assertNotNull($response->json('schools.0.tanggal_skrining_terakhir'));
    }
}
