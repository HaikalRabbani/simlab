<?php

namespace App\Http\Controllers;

use App\Models\Examination;
use App\Support\DashboardData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DemographicsController extends Controller
{
    public function byGender(Request $request): JsonResponse
    {
        $this->authorizeDinas($request);
        [$mulai, $sampai] = DashboardData::periodeRange($request);
        $wilayah = $request->input('wilayah', 'semua');
        $jenjang = $request->input('jenjang', 'semua');

        $rows = DashboardData::joinedScope($mulai, $sampai, $wilayah, $jenjang)
            ->selectRaw('st.jenis_kelamin as jenis_kelamin')
            ->addSelect(DB::raw('count(e.id) as total_diuji'))
            ->addSelect(DB::raw('sum(case when '.DashboardData::reactiveExistsSql().' then 1 else 0 end) as jumlah_reaktif'))
            ->groupBy('st.jenis_kelamin')
            ->get()
            ->keyBy('jenis_kelamin');

        $genders = ['L', 'P'];
        $data = collect($genders)->map(function ($gender) use ($rows) {
            $row = $rows->get($gender);
            $total = (int) ($row->total_diuji ?? 0);
            $reaktif = (int) ($row->jumlah_reaktif ?? 0);

            return [
                'jenis_kelamin' => $gender,
                'label' => $gender === 'L' ? 'Laki-laki' : 'Perempuan',
                'jumlah_diuji' => $total,
                'jumlah_reaktif' => $reaktif,
                'persentase_reaktif' => $total > 0 ? round($reaktif / $total * 100, 2) : 0.0,
            ];
        });

        $laki = $data[0];
        $perempuan = $data[1];

        return response()->json([
            'total_diperiksa' => $data->sum('jumlah_diuji'),
            'periode' => ['mulai' => $mulai->toDateString(), 'sampai' => $sampai->toDateString()],
            'filter' => ['wilayah' => $wilayah, 'jenjang' => $jenjang],
            'insight' => $this->insightGender($laki, $perempuan),
            'data' => $data,
        ]);
    }

    public function byGrade(Request $request): JsonResponse
    {
        $this->authorizeDinas($request);
        [$mulai, $sampai] = DashboardData::periodeRange($request);
        $wilayah = $request->input('wilayah', 'semua');
        $jenjang = $request->input('jenjang', 'semua');

        $rows = DashboardData::joinedScope($mulai, $sampai, $wilayah, $jenjang)
            ->selectRaw('st.kelas as kelas, sc.jenjang as jenjang')
            ->addSelect(DB::raw('count(e.id) as total_diuji'))
            ->addSelect(DB::raw('sum(case when '.DashboardData::reactiveExistsSql().' then 1 else 0 end) as jumlah_reaktif'))
            ->groupBy('st.kelas', 'sc.jenjang')
            ->get();

        $agregat = function ($keyOf) use ($rows) {
            $groups = [];
            foreach ($rows as $row) {
                $key = $keyOf($row);
                $groups[$key]['total'] = ($groups[$key]['total'] ?? 0) + (int) $row->total_diuji;
                $groups[$key]['reaktif'] = ($groups[$key]['reaktif'] ?? 0) + (int) $row->jumlah_reaktif;
            }

            return $groups;
        };

        $build = function (array $groups, array $order, array $labels) {
            return collect($order)->map(function ($key) use ($groups, $labels) {
                $total = (int) ($groups[$key]['total'] ?? 0);
                $reaktif = (int) ($groups[$key]['reaktif'] ?? 0);

                return [
                    'kategori' => $key,
                    'label' => $labels[$key] ?? $key,
                    'jumlah_diuji' => $total,
                    'jumlah_reaktif' => $reaktif,
                    'persentase_reaktif' => $total > 0 ? round($reaktif / $total * 100, 2) : 0.0,
                ];
            })->values();
        };

        $kelasData = $build($agregat(fn ($r) => DashboardData::kelasGroup($r->kelas)), ['X', 'XI', 'XII', 'lainnya'], [
            'X' => 'Kelas X', 'XI' => 'Kelas XI', 'XII' => 'Kelas XII', 'lainnya' => 'Lainnya',
        ]);

        $jenjangData = $build($agregat(fn ($r) => $r->jenjang), ['SMA', 'SMK', 'MA'], [
            'SMA' => 'SMA', 'SMK' => 'SMK', 'MA' => 'MA',
        ]);

        $find = fn ($col, $kategori) => collect($col)->firstWhere('kategori', $kategori);

        return response()->json([
            'total_diperiksa' => $kelasData->sum('jumlah_diuji'),
            'periode' => ['mulai' => $mulai->toDateString(), 'sampai' => $sampai->toDateString()],
            'filter' => ['wilayah' => $wilayah, 'jenjang' => $jenjang],
            'insight' => $this->insightGrade($find($kelasData, 'X'), $find($kelasData, 'XI'), $find($kelasData, 'XII'), $find($jenjangData, 'SMK'), $find($jenjangData, 'SMA')),
            'data' => [
                'kelas' => $kelasData,
                'jenjang' => $jenjangData,
            ],
        ]);
    }

    public function crossTable(Request $request): JsonResponse
    {
        $this->authorizeDinas($request);
        [$mulai, $sampai] = DashboardData::periodeRange($request);
        $wilayah = $request->input('wilayah', 'semua');
        $jenjang = $request->input('jenjang', 'semua');

        $rows = DashboardData::joinedScope($mulai, $sampai, $wilayah, $jenjang)
            ->join('examination_results as er', 'er.examination_id', '=', 'e.id')
            ->where('er.hasil', 'positif')
            ->selectRaw('er.parameter as parameter, st.jenis_kelamin as jenis_kelamin, sc.jenjang as jenjang, st.kelas as kelas')
            ->get();

        $rowsByParameter = $rows->groupBy('parameter');

        $hitung = function ($subset, string $field, string $value) {
            return $subset->filter(fn ($row) => $row->{$field} === $value)->count();
        };

        $hitungKelas = function ($subset, string $group) {
            return $subset->filter(fn ($row) => DashboardData::kelasGroup($row->kelas) === $group)->count();
        };

        $rowsByParam = collect(Examination::PARAMETERS)->map(function ($parameter) use ($rowsByParameter, $hitung, $hitungKelas) {
            $subset = $rowsByParameter[$parameter] ?? collect();

            return [
                'parameter' => $parameter,
                'label' => DashboardController::labelParameter($parameter),
                'laki_laki' => $hitung($subset, 'jenis_kelamin', 'L'),
                'perempuan' => $hitung($subset, 'jenis_kelamin', 'P'),
                'sma' => $hitung($subset, 'jenjang', 'SMA'),
                'smk' => $hitung($subset, 'jenjang', 'SMK'),
                'ma' => $hitung($subset, 'jenjang', 'MA'),
                'kelas_x' => $hitungKelas($subset, 'X'),
                'kelas_xi' => $hitungKelas($subset, 'XI'),
                'kelas_xii' => $hitungKelas($subset, 'XII'),
                'total' => $subset->count(),
            ];
        });

        return response()->json([
            'periode' => ['mulai' => $mulai->toDateString(), 'sampai' => $sampai->toDateString()],
            'filter' => ['wilayah' => $wilayah, 'jenjang' => $jenjang],
            'catatan' => 'Jumlah hasil reaktif · satu siswa dapat reaktif di lebih dari satu parameter.',
            'rows' => $rowsByParam,
        ]);
    }

    private function insightGender(array $laki, array $perempuan): ?string
    {
        $total = $laki['jumlah_diuji'] + $perempuan['jumlah_diuji'];
        if ($total === 0) {
            return null;
        }

        $pL = $laki['persentase_reaktif'];
        $pP = $perempuan['persentase_reaktif'];

        if ($pL > 0 && $pP > 0 && $pL / $pP >= 1.5) {
            return "Temuan pada siswa laki-laki ".round($pL / $pP, 1)." kali lebih tinggi daripada perempuan. Selisih ini dapat menjadi dasar penyesuaian sasaran program pencegahan.";
        }
        if ($pP > $pL && $pL > 0 && $pP / $pL >= 1.5) {
            return "Temuan pada siswa perempuan ".round($pP / $pL, 1)." kali lebih tinggi daripada laki-laki. Perlu penelusuran lebih lanjut.";
        }

        return 'Proporsi temuan laki-laki dan perempuan relatif seimbang pada periode ini.';
    }

    private function insightGrade(?array $x, ?array $xi, ?array $xii, ?array $smk, ?array $sma): ?string
    {
        $pX = (float) ($x['persentase_reaktif'] ?? 0);
        $pXI = (float) ($xi['persentase_reaktif'] ?? 0);
        $pXII = (float) ($xii['persentase_reaktif'] ?? 0);
        $pSmk = (float) ($smk['persentase_reaktif'] ?? 0);
        $pSma = (float) ($sma['persentase_reaktif'] ?? 0);

        $total = (int) ($x['jumlah_diuji'] ?? 0) + (int) ($xi['jumlah_diuji'] ?? 0) + (int) ($xii['jumlah_diuji'] ?? 0);
        if ($total === 0 && $pSmk === 0 && $pSma === 0) {
            return null;
        }

        $kalimat = [];
        if ($pXII > $pXI && $pXI > $pX && $pX > 0) {
            $kalimat[] = 'Temuan cenderung naik seiring jenjang kelas (X → XI → XII).';
        } elseif ($pX > 0 && $pXII > 0) {
            $kalimat[] = 'Distribusi temuan antar kelas relatif bervariasi — cermati kelas dengan persentase tertinggi.';
        }
        if ($pSmk > 0 && $pSma > 0 && $pSmk / $pSma >= 1.2) {
            $kalimat[] = 'Persentase temuan di SMK '.round($pSmk / $pSma, 1)." kali lebih tinggi dibanding SMA.";
        } elseif ($pSma > 0 && $pSmk > 0 && $pSma / $pSmk >= 1.2) {
            $kalimat[] = 'Persentase temuan di SMA lebih tinggi dibanding SMK pada periode ini.';
        }

        return $kalimat === [] ? 'Temuan tersebar relatif merata di jenjang kelas pada periode ini.' : implode(' ', $kalimat);
    }

    private function authorizeDinas(Request $request): void
    {
        abort_unless($request->user()->isDinas(), Response::HTTP_FORBIDDEN, 'Hanya role Dinas Provinsi yang diizinkan.');
    }
}
