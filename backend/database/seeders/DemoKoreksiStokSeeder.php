<?php

namespace Database\Seeders;

use App\Models\CorrectionRequest;
use App\Models\Examination;
use App\Models\TestStripStock;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Data demo untuk Panel Pengawas (spec 8.6/10) & Stok Alat Uji (spec 5.1).
 *
 * - Koreksi: beberapa pemeriksaan terkunci milik petugas Kota Bandung yang
 *   diajukan koreksi, agar pengawas@simlab.test punya antrean "menunggu".
 * - Stok: catatan stok strip milik petugas@simlab.test untuk 7 parameter.
 *
 * Aman dijalankan berulang (tidak menduplikasi bila data sudah ada).
 */
class DemoKoreksiStokSeeder extends Seeder
{
    public function run(): void
    {
        // Petugas dengan riwayat pemeriksaan di Kota Bandung (wilayah pengawas demo)
        $petugasBerriwayat = User::where('email', 'petugas.kota-bandung@simlab.test')
            ->first()
            ?? User::where('role', User::ROLE_PETUGAS)->orderBy('id')->first();
        // Petugas utama demo (untuk modul stok)
        $petugasStok = User::where('email', 'petugas@simlab.test')->first();

        if (! $petugasBerriwayat || ! $petugasStok) {
            $this->command?->warn('DemoKoreksiStokSeeder: akun petugas demo belum ada — dilewati.');

            return;
        }

        $this->buatKoreksi($petugasBerriwayat);
        $this->buatStok($petugasStok);
    }

    private function buatKoreksi(User $petugas): void
    {
        if (CorrectionRequest::exists()) {
            return; // sudah pernah di-seed
        }

        // Ambil pemeriksaan terkunci milik petugas ini (Kota Bandung)
        $examinations = Examination::with('student:id,nama')
            ->where('petugas_id', $petugas->id)
            ->where('is_locked', true)
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        $alasan = [
            'Salah input nama siswa, nama tertulis terbalik dengan nama belakang.',
            'Kode segel sampel salah ketik, mohon dikoreksi.',
            'Tanggal lahir siswa tidak sesuai data Dapodik.',
            'Hasil parameter THC seharusnya invalid (strip rusak), bukan negatif.',
        ];
        $statuses = ['menunggu', 'menunggu', 'menunggu', 'disetujui', 'disetujui', 'ditolak'];

        foreach ($examinations as $i => $exam) {
            $status = $statuses[$i % count($statuses)];
            $data = [
                'examination_id' => $exam->id,
                'diajukan_oleh' => $petugas->id,
                'alasan' => $alasan[$i % count($alasan)],
                'status' => $status,
                'created_at' => Carbon::now()->subDays(1 + $i),
                'updated_at' => Carbon::now()->subDays(1 + $i),
            ];

            if ($status !== 'menunggu') {
                $data['ditinjau_oleh'] = User::where('role', User::ROLE_PENGAWAS)->first()?->id;
                $data['catatan_peninjau'] = $status === 'disetujui'
                    ? 'Data diperbaiki, silakan sinkronkan ulang.'
                    : 'Identitas sudah sesuai dengan data sekolah, tidak perlu dikoreksi.';
                $data['updated_at'] = Carbon::now()->subHours(rand(2, 20));
            }

            CorrectionRequest::create($data);
        }

        $this->command?->info('Demo koreksi dibuat: '.CorrectionRequest::count().' pengajuan.');
    }

    private function buatStok(User $petugas): void
    {
        $lama = (int) TestStripStock::where('petugas_id', $petugas->id)->count();
        if ($lama > 0) {
            return;
        }

        // Sekolah pertama di wilayah petugas (Kota Bandung)
        $school = \App\Models\School::where('kab_kota', $petugas->wilayah_scope)->first();

        foreach (Examination::PARAMETERS as $index => $parameter) {
            TestStripStock::create([
                'petugas_id' => $petugas->id,
                'school_id' => $school?->id,
                'parameter' => $parameter,
                'jumlah_stok' => rand(20, 120),
                'lot_code' => 'STRIP-'.Carbon::now()->format('ym').'-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                'expiry_date' => Carbon::today()->addMonths(rand(3, 11))->toDateString(),
            ]);
        }

        $this->command?->info('Demo stok dibuat: 7 catatan untuk petugas@simlab.test.');
    }
}
