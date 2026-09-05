<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->dinas()->create([
            'nama' => 'Drs. Hendra Gunawan',
            'email' => 'dinas@simlab.test',
            'wilayah_scope' => null,
        ]);

        User::factory()->pengawas('Kota Bandung')->create([
            'nama' => 'Sari Puspita',
            'email' => 'pengawas@simlab.test',
        ]);

        User::factory()->petugas('Kota Bandung')->create([
            'nama' => 'Rina Kusmawati',
            'email' => 'petugas@simlab.test',
        ]);

        // Data demo lengkap: 27 kab/kota x 3 jenjang sekolah + siswa + pemeriksaan.
        // Otomatis dilewati bila data sekolah sudah ada (lihat SimlabSeeder).
        $this->call(SimlabSeeder::class);
    }
}
