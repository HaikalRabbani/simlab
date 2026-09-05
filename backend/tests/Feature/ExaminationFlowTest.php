<?php

namespace Tests\Feature;

use App\Models\Examination;
use App\Models\ExaminationResult;
use App\Models\School;
use App\Models\ScreeningSchedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExaminationFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeSchool(array $overrides = []): School
    {
        return School::create(array_merge([
            'npsn' => '10000001',
            'nama' => 'SMA Negeri 12 Bandung',
            'kab_kota' => 'Kota Bandung',
            'kecamatan' => 'Coblong',
            'wilayah_group' => 'Bandung Raya',
            'jenjang' => 'SMA',
            'target_siswa' => 180,
        ], $overrides));
    }

    private function makeStudent(School $school, array $overrides = []): Student
    {
        return Student::create(array_merge([
            'nisn' => '0098765432',
            'nama' => 'Ahmad Fauzi',
            'kelas' => 'XI IPA 3',
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '2008-05-12',
            'school_id' => $school->id,
        ], $overrides));
    }

    private function schedulePetugas(User $petugas, School $school): ScreeningSchedule
    {
        return ScreeningSchedule::create([
            'school_id' => $school->id,
            'petugas_id' => $petugas->id,
            'tanggal' => now()->toDateString(),
            'sesi' => 'pagi',
            'target_siswa' => 60,
            'status' => 'berlangsung',
        ]);
    }

    private function hasil(string $nilai = 'negatif', string $parameter = 'THC'): array
    {
        $parameters = ['THC', 'AMP', 'MET', 'MOP', 'BZO', 'TRA', 'ALKOHOL'];

        return collect($parameters)->map(fn ($p) => [
            'parameter' => $p,
            'hasil' => $p === $parameter ? $nilai : 'negatif',
        ])->values()->all();
    }

    private function payload(Student $student, array $overrides = []): array
    {
        return array_merge([
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'student_id' => $student->id,
            'pendamping' => 'Guru BK — Dra. Siti Rohmah',
            'strip_lot_code' => 'RT-2609-A',
            'strip_expiry_date' => now()->addMonths(6)->toDateString(),
            'hasil' => $this->hasil('negatif'),
        ], $overrides);
    }

    public function test_petugas_mengirim_pemeriksaan_negatif_terkunci(): void
    {
        $petugas = User::factory()->petugas('Kota Bandung')->create();
        $school = $this->makeSchool();
        $student = $this->makeStudent($school);
        $this->schedulePetugas($petugas, $school);

        Sanctum::actingAs($petugas);

        $this->postJson('/api/examinations', $this->payload($student))
            ->assertCreated()
            ->assertJsonPath('status_kirim', 'terkirim')
            ->assertJsonPath('is_locked', true)
            ->assertJsonPath('hasil_ringkasan', 'Negatif semua');

        $this->assertDatabaseCount('examinations', 1);
        $this->assertDatabaseCount('examination_results', 7);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_reaktif_wajib_rencana_tindak_lanjut_dan_sampel_disegel(): void
    {
        $petugas = User::factory()->petugas('Kota Bandung')->create();
        $school = $this->makeSchool();
        $student = $this->makeStudent($school);
        $this->schedulePetugas($petugas, $school);

        Sanctum::actingAs($petugas);

        // Tanpa field tindak lanjut -> ditolak
        $this->postJson('/api/examinations', $this->payload($student, ['hasil' => $this->hasil('positif', 'THC')]))
            ->assertStatus(422);

        // Lengkap -> berhasil, parameter reaktif terbaca
        $this->postJson('/api/examinations', $this->payload($student, [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'hasil' => $this->hasil('positif', 'THC'),
            'rencana_tindak_lanjut' => 'rujuk_uji_konfirmasi',
            'sampel_disegel' => true,
            'kode_segel' => 'SG-0412',
            'catatan_petugas' => 'Sampel disegel untuk uji lanjut.',
        ]))->assertCreated()
            ->assertJsonPath('rencana_tindak_lanjut', 'rujuk_uji_konfirmasi')
            ->assertJsonCount(1, 'reactive_parameters')
            ->assertJsonPath('reactive_parameters.0', 'THC');
    }

    public function test_hasil_duplikat_parameter_ditolak(): void
    {
        $petugas = User::factory()->petugas('Kota Bandung')->create();
        $school = $this->makeSchool();
        $student = $this->makeStudent($school);
        $this->schedulePetugas($petugas, $school);

        Sanctum::actingAs($petugas);

        $hasil = $this->hasil('negatif');
        $hasil[1] = $hasil[0]; // AMP diisi THC lagi

        $this->postJson('/api/examinations', $this->payload($student, ['hasil' => $hasil]))
            ->assertStatus(422);
    }

    public function test_submit_idempoten_untuk_retry_offline(): void
    {
        $petugas = User::factory()->petugas('Kota Bandung')->create();
        $school = $this->makeSchool();
        $student = $this->makeStudent($school);
        $this->schedulePetugas($petugas, $school);

        Sanctum::actingAs($petugas);

        $uuid = (string) \Illuminate\Support\Str::uuid();
        $payload = $this->payload($student, ['client_uuid' => $uuid]);

        $this->postJson('/api/examinations', $payload)->assertCreated();
        $this->postJson('/api/examinations', $payload)->assertOk();

        $this->assertDatabaseCount('examinations', 1);
    }

    public function test_sekolah_tidak_terjadwal_ditolak(): void
    {
        $petugas = User::factory()->petugas('Kota Bandung')->create();
        $school = $this->makeSchool();
        $student = $this->makeStudent($school);
        // Tidak ada jadwal untuk petugas ini

        Sanctum::actingAs($petugas);

        $this->postJson('/api/examinations', $this->payload($student))
            ->assertStatus(422);
    }

    public function test_dinas_tidak_bisa_menginput_atau_melihat_daftar_pemeriksaan(): void
    {
        $dinas = User::factory()->dinas()->create();
        $school = $this->makeSchool();
        $student = $this->makeStudent($school);

        Sanctum::actingAs($dinas);

        $this->postJson('/api/examinations', $this->payload($student))->assertStatus(403);
        $this->getJson('/api/examinations')->assertStatus(403);
        $this->getJson('/api/students')->assertStatus(403);
    }

    public function test_pengawas_wilayah_lain_tidak_bisa_melihat_detail_pemeriksaan(): void
    {
        $petugas = User::factory()->petugas('Kota Bandung')->create();
        $school = $this->makeSchool();
        $student = $this->makeStudent($school);
        $this->schedulePetugas($petugas, $school);

        Sanctum::actingAs($petugas);
        $examination = $this->postJson('/api/examinations', $this->payload($student))->json('id');

        // Pengawas dari kabupaten lain
        $pengawasLain = User::factory()->pengawas('Kabupaten Bogor')->create();
        Sanctum::actingAs($pengawasLain);

        $this->getJson("/api/examinations/{$examination}")->assertStatus(403);
    }

    public function test_alur_koreksi_disetujui_pengawas_wilayah(): void
    {
        $petugas = User::factory()->petugas('Kota Bandung')->create();
        $pengawas = User::factory()->pengawas('Kota Bandung')->create();
        $school = $this->makeSchool();
        $student = $this->makeStudent($school);
        $this->schedulePetugas($petugas, $school);

        Sanctum::actingAs($petugas);
        $examinationId = $this->postJson('/api/examinations', $this->payload($student))->json('id');

        // Pengajuan koreksi oleh petugas
        $correction = $this->postJson("/api/examinations/{$examinationId}/correction-request", [
            'alasan' => 'Kelas siswa salah ketik.',
        ])->assertCreated()->json();

        $this->assertDatabaseHas('correction_requests', ['id' => $correction['id'], 'status' => 'menunggu']);

        // Pengawas wilayah yang benar menyetujui
        Sanctum::actingAs($pengawas);
        $this->postJson("/api/correction-requests/{$correction['id']}/approve", [
            'catatan_peninjau' => 'Disetujui, data dikoreksi.',
        ])->assertOk()
            ->assertJsonPath('status', 'disetujui')
            ->assertJsonPath('ditinjau_oleh', $pengawas->id);

        $this->assertDatabaseCount('audit_logs', 3); // submit + request_correction + approve
    }

    public function test_riwayat_petugas_hanya_data_sendiri_dan_bisa_diffilter_tanggal(): void
    {
        $petugas = User::factory()->petugas('Kota Bandung')->create();
        $petugasLain = User::factory()->petugas('Kota Bandung')->create();
        $school = $this->makeSchool();
        $student = $this->makeStudent($school);
        $this->schedulePetugas($petugas, $school);
        $this->schedulePetugas($petugasLain, $school);

        Sanctum::actingAs($petugas);
        $this->postJson('/api/examinations', $this->payload($student))->assertCreated();

        Sanctum::actingAs($petugasLain);
        $this->postJson('/api/examinations', $this->payload($student, [
            'client_uuid' => (string) \Illuminate\Support\Str::uuid(),
        ]))->assertCreated();

        Sanctum::actingAs($petugas);
        $this->getJson('/api/examinations?tanggal='.now()->toDateString())
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.petugas_id', $petugas->id);
    }

    public function test_siswa_dengan_riwayat_pemeriksaan_tidak_bisa_dihapus(): void
    {
        $petugas = User::factory()->petugas('Kota Bandung')->create();
        $school = $this->makeSchool();
        $student = $this->makeStudent($school);
        $this->schedulePetugas($petugas, $school);

        Sanctum::actingAs($petugas);
        $this->postJson('/api/examinations', $this->payload($student))->assertCreated();

        // Penghapusan siswa yang sudah punya pemeriksaan terkirim harus ditolak
        // (cascade akan menghapus examination immutable & merusak jejak audit).
        $this->deleteJson("/api/students/{$student->id}")->assertStatus(422);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseCount('examinations', 1);
    }

    public function test_siswa_tanpa_riwayat_masih_bisa_dihapus(): void
    {
        $petugas = User::factory()->petugas('Kota Bandung')->create();
        $school = $this->makeSchool();
        $student = $this->makeStudent($school);
        $this->schedulePetugas($petugas, $school);

        Sanctum::actingAs($petugas);
        $this->deleteJson("/api/students/{$student->id}")->assertOk();
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_schedule_hanya_bisa_dibuat_dinas(): void
    {
        $petugas = User::factory()->petugas('Kota Bandung')->create();
        $school = $this->makeSchool();
        $this->schedulePetugas($petugas, $school);

        Sanctum::actingAs($petugas);
        $this->postJson('/api/schedules', [
            'school_id' => $school->id,
            'petugas_id' => $petugas->id,
            'tanggal' => now()->addDay()->toDateString(),
            'sesi' => 'siang',
            'target_siswa' => 50,
        ])->assertStatus(403);

        $dinas = User::factory()->dinas()->create();
        Sanctum::actingAs($dinas);
        $this->postJson('/api/schedules', [
            'school_id' => $school->id,
            'petugas_id' => $petugas->id,
            'tanggal' => now()->addDay()->toDateString(),
            'sesi' => 'siang',
            'target_siswa' => 50,
        ])->assertCreated();
    }
}
