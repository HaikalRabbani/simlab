<?php

namespace App\Http\Controllers;

use App\Models\Examination;
use App\Support\DashboardData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $this->authorizeDinas($request);
        [$mulai, $sampai] = DashboardData::periodeRange($request);

        // Semua agregasi di level SQL agar tetap cepat walau puluhan ribu pemeriksaan
        $scope = DashboardData::joinedScope($mulai, $sampai);

        $total = (int) (clone $scope)->count('e.id');
        $reaktif = (int) (clone $scope)
            ->whereRaw(DashboardData::reactiveExistsSql())
            ->count('e.id');
        $sudahKonfirmasi = (int) (clone $scope)
            ->whereRaw(DashboardData::reactiveExistsSql())
            ->where('e.rencana_tindak_lanjut', 'rujuk_uji_konfirmasi')
            ->count('e.id');
        $sekolahTerjangkau = (int) (clone $scope)->distinct()->count('st.school_id');

        // Jumlah petugas aktif & kab/kota tempat mereka bertugas
        $petugasRow = (clone $scope)
            ->selectRaw('count(distinct e.petugas_id) as petugas')
            ->selectRaw('count(distinct sc.kab_kota) as kab_kota')
            ->first();
        $petugasAktif = (int) ($petugasRow->petugas ?? 0);
        $kabPetugas = (int) ($petugasRow->kab_kota ?? 0);

        $targetTahunan = DashboardData::targetTahunan();
        $persentaseCapaian = $targetTahunan > 0 ? round($total / $targetTahunan * 100, 1) : 0;
        $persentaseReaktif = $total > 0 ? round($reaktif / $total * 100, 2) : 0;

        return response()->json([
            'periode' => ['mulai' => $mulai->toDateString(), 'sampai' => $sampai->toDateString()],
            'diperbarui' => now()->toISOString(),
            'jumlah_kab_kota' => DashboardData::jumlahKabKota(),
            'cards' => [
                'siswa_diperiksa' => $total,
                'target_tahunan' => $targetTahunan,
                'persentase_capaian' => $persentaseCapaian,
                'sekolah_terjangkau' => $sekolahTerjangkau,
                'total_sekolah_sasaran' => DB::table('schools')->count(),
                'hasil_reaktif' => $reaktif,
                'persentase_reaktif' => $persentaseReaktif,
                'sudah_uji_konfirmasi' => $sudahKonfirmasi,
                'sisa_menunggu_lab' => max($reaktif - $sudahKonfirmasi, 0),
                'petugas_aktif' => $petugasAktif,
                'kab_kota_petugas' => $kabPetugas,
            ],
        ]);
    }

    /**
     * Heatmap tingkat hasil reaktif per kab/kota (spec 6.3).
     */
    public function reactiveByRegion(Request $request): JsonResponse
    {
        $this->authorizeDinas($request);
        [$mulai, $sampai] = DashboardData::periodeRange($request);

        $rows = DashboardData::joinedScope($mulai, $sampai)
            ->selectRaw('sc.kab_kota as kab_kota')
            ->addSelect(DB::raw('count(e.id) as total_diuji'))
            ->addSelect(DB::raw('sum(case when '.DashboardData::reactiveExistsSql().' then 1 else 0 end) as jumlah_reaktif'))
            ->groupBy('sc.kab_kota')
            ->orderBy('sc.kab_kota')
            ->get();

        $byKab = $rows->keyBy('kab_kota');

        // Selalu tampilkan seluruh 27 kab/kota Jawa Barat (spec 6.3);
        // wilayah tanpa pemeriksaan pada periode ini tampil dengan angka 0.
        // Nama wilayah digabung dari data yang ada + daftar resmi agar tidak dobel
        // dan tetap menampilkan wilayah yang namanya beda tipis dari daftar resmi.
        $semuaKab = collect($rows->pluck('kab_kota'))
            ->merge(DashboardData::KAB_KOTA_JABAR)
            ->unique()
            ->values();

        $regions = $semuaKab->map(function (string $kab) use ($byKab) {
            $row = $byKab->get($kab);
            $total = (int) ($row->total_diuji ?? 0);
            $reaktif = (int) ($row->jumlah_reaktif ?? 0);
            $persentase = $total > 0 ? round($reaktif / $total * 100, 2) : 0.0;

            return [
                'kab_kota' => $kab,
                'jumlah_diuji' => $total,
                'jumlah_reaktif' => $reaktif,
                'persentase_reaktif' => $persentase,
                'tingkat' => $total > 0 ? DashboardData::tingkatReaktif($persentase) : 'rendah',
            ];
        })->values();

        return response()->json([
            'legenda' => DashboardData::TINGKAT,
            'wilayah' => $regions,
        ]);
    }

    /**
     * Jumlah reaktif per jenis parameter (spec 6.3).
     */
    public function reactiveByParameter(Request $request): JsonResponse
    {
        $this->authorizeDinas($request);
        [$mulai, $sampai] = DashboardData::periodeRange($request);

        $rows = DashboardData::joinedScope($mulai, $sampai)
            ->join('examination_results as er', 'er.examination_id', '=', 'e.id')
            ->where('er.hasil', 'positif')
            ->selectRaw('er.parameter as parameter')
            ->addSelect(DB::raw('count(*) as jumlah_reaktif'))
            ->groupBy('er.parameter')
            ->orderByDesc('jumlah_reaktif')
            ->get()
            ->keyBy('parameter');

        $totalReaktif = DB::table('examination_results as er')
            ->where('er.hasil', 'positif')
            ->whereExists(function ($sub) use ($mulai, $sampai) {
                $sub->selectRaw('1')
                    ->from('examinations as e2')
                    ->whereColumn('e2.id', 'er.examination_id')
                    ->whereBetween('e2.waktu_input', [$mulai, $sampai]);
            })
            ->count('er.id');

        $parameters = collect(Examination::PARAMETERS)->map(function ($parameter) use ($rows) {
            return [
                'parameter' => $parameter,
                'label' => self::labelParameter($parameter),
                'jumlah_reaktif' => (int) ($rows[$parameter]->jumlah_reaktif ?? 0),
            ];
        })->sortByDesc('jumlah_reaktif')->values();

        return response()->json([
            'total_hasil_reaktif' => $totalReaktif,
            'catatan' => 'Satu siswa dapat reaktif pada lebih dari satu parameter. Angka ini hasil skrining awal — status akhir mengikuti uji konfirmasi laboratorium.',
            'parameters' => $parameters,
        ]);
    }

    /**
     * Sekolah dengan temuan tertinggi (top ~7) untuk prioritas pendampingan.
     */
    public function topSchools(Request $request): JsonResponse
    {
        $this->authorizeDinas($request);
        [$mulai, $sampai] = DashboardData::periodeRange($request);

        $rows = DashboardData::joinedScope($mulai, $sampai)
            ->selectRaw('sc.id as school_id, sc.nama as nama, sc.kab_kota as kab_kota')
            ->addSelect(DB::raw('count(e.id) as jumlah_diuji'))
            ->addSelect(DB::raw('sum(case when '.DashboardData::reactiveExistsSql().' then 1 else 0 end) as jumlah_reaktif'))
            ->groupBy('sc.id', 'sc.nama', 'sc.kab_kota')
            ->havingRaw('count(e.id) > 0')
            ->orderByDesc('jumlah_reaktif')
            ->orderByDesc('jumlah_diuji')
            ->limit(7)
            ->get();

        $schools = $rows->map(function ($row) {
            $total = (int) $row->jumlah_diuji;
            $reaktif = (int) $row->jumlah_reaktif;

            return [
                'id' => $row->school_id,
                'nama' => $row->nama,
                'kab_kota' => $row->kab_kota,
                'jumlah_diuji' => $total,
                'jumlah_reaktif' => $reaktif,
                'persentase_reaktif' => $total > 0 ? round($reaktif / $total * 100, 2) : 0.0,
            ];
        })->values();

        return response()->json(['schools' => $schools]);
    }

    public static function labelParameter(string $parameter): string
    {
        return match ($parameter) {
            'THC' => 'THC (ganja)',
            'AMP' => 'AMP (amfetamin)',
            'MET' => 'MET (sabu)',
            'MOP' => 'MOP (morfin/opiat)',
            'BZO' => 'BZO (benzodiazepin)',
            'TRA' => 'TRA (tramadol)',
            'ALKOHOL' => 'Alkohol (etanol saliva)',
            default => $parameter,
        };
    }

    private function authorizeDinas(Request $request): void
    {
        abort_unless($request->user()->isDinas(), Response::HTTP_FORBIDDEN, 'Hanya role Dinas Provinsi yang diizinkan.');
    }
}
