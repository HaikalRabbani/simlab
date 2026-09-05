<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Helper agregasi untuk Dashboard & Analisis Demografi (spec 6.3, 6.4).
 * Semua angka berbasis examination (satu pemeriksaan = satu siswa diskrining).
 */
class DashboardData
{
    /** Threshold tingkat reaktif per kab/kota (spec 6.3). */
    public const TINGKAT = [
        'rendah' => '< 0.7%',
        'sedang' => '0.7% – 1.6%',
        'tinggi' => '≥ 1.6%',
    ];

    /**
     * Daftar resmi 27 kab/kota Jawa Barat — dipakai supaya heatmap Dashboard
     * menampilkan seluruh wilayah walau belum ada data (spec 6.3).
     */
    public const KAB_KOTA_JABAR = [
        // Bandung Raya
        'Kota Bandung', 'Kab. Bandung', 'Kab. Bandung Barat', 'Kota Cimahi', 'Kab. Sumedang',
        // Bodebek
        'Kab. Bogor', 'Kota Bogor', 'Kota Depok', 'Kab. Bekasi', 'Kota Bekasi',
        // Pantura
        'Kab. Karawang', 'Kab. Purwakarta', 'Kab. Subang', 'Kab. Indramayu', 'Kab. Cirebon', 'Kota Cirebon',
        // Priangan Timur
        'Kab. Sukabumi', 'Kota Sukabumi', 'Kab. Cianjur', 'Kab. Garut', 'Kab. Tasikmalaya',
        'Kota Tasikmalaya', 'Kab. Ciamis', 'Kota Banjar', 'Kab. Pangandaran', 'Kab. Kuningan', 'Kab. Majalengka',
    ];

    /**
     * Rentang periode dari query param: mulai/sampai, atau tahun.
     */
    public static function periodeRange(Request $request): array
    {
        $sampai = $request->filled('sampai')
            ? Carbon::parse($request->input('sampai'))
            : now();

        $mulai = $request->filled('mulai')
            ? Carbon::parse($request->input('mulai'))
            : Carbon::create($request->integer('tahun', $sampai->year), 1, 1);

        if ($mulai->gt($sampai)) {
            [$mulai, $sampai] = [$sampai, $mulai];
        }

        return [$mulai->startOfDay(), $sampai->endOfDay()];
    }

    /**
     * Scope dasar agregasi: examinations e + students st + schools sc,
     * dibatasi periode serta (opsional) wilayah_group & jenjang sekolah.
     */
    public static function joinedScope(
        Carbon $mulai,
        Carbon $sampai,
        ?string $wilayah = null,
        ?string $jenjang = null
    ): Builder {
        $q = DB::table('examinations as e')
            ->join('students as st', 'st.id', '=', 'e.student_id')
            ->join('schools as sc', 'sc.id', '=', 'st.school_id')
            ->whereBetween('e.waktu_input', [$mulai, $sampai]);

        if ($wilayah && $wilayah !== 'semua') {
            $q->where('sc.wilayah_group', $wilayah);
        }
        if ($jenjang && $jenjang !== 'semua') {
            $q->where('sc.jenjang', $jenjang);
        }

        return $q;
    }

    /**
     * SQL: 1 bila examination punya >=1 hasil positif (reaktif), else 0.
     */
    public static function reactiveExistsSql(): string
    {
        return "exists(select 1 from examination_results er where er.examination_id = e.id and er.hasil = 'positif')";
    }

    /**
     * Kelompok kelas dari string kelas siswa, contoh "XI IPA 3" -> "XI".
     */
    public static function kelasGroup(string $kelas): string
    {
        $kelas = strtoupper(trim($kelas));
        // Angka romawi: XII / XI / X (urutan penting — XII diawali XI dan X)
        foreach (['XII', 'XI', 'X'] as $prefix) {
            if (str_starts_with($kelas, $prefix)) {
                return $prefix;
            }
        }

        return 'lainnya';
    }

    public static function tingkatReaktif(float $persentase): string
    {
        if ($persentase >= 1.6) {
            return 'tinggi';
        }
        if ($persentase >= 0.7) {
            return 'sedang';
        }

        return 'rendah';
    }

    /**
     * Jumlah kab/kota yang terdata di tabel sekolah.
     */
    public static function jumlahKabKota(): int
    {
        return (int) DB::table('schools')->distinct()->count('kab_kota');
    }

    /**
     * Total target siswa seluruh sekolah sasaran (dipakai hitung capaian).
     */
    public static function targetTahunan(): int
    {
        return (int) DB::table('schools')->sum('target_siswa');
    }
}
