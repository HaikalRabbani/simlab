<?php

namespace App\Http\Controllers;

use App\Models\Examination;
use App\Models\School;
use App\Support\DashboardData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laporan Periodik & Ekspor Data (spec 5.2) — ekspor CSV dari data agregat.
 * Khusus role Dinas Provinsi; tidak pernah mengekspor identitas siswa.
 */
class ExportController extends Controller
{
    private const TYPES = ['regions', 'schools', 'parameters'];

    public function export(Request $request, string $type): Response
    {
        $this->authorizeDinas($request);

        if (! in_array($type, self::TYPES, true)) {
            return response()->json(['message' => 'Tipe ekspor tidak dikenal.'], Response::HTTP_NOT_FOUND);
        }

        [$mulai, $sampai] = DashboardData::periodeRange($request);

        [$headers, $rows] = match ($type) {
            'regions' => $this->dataRegions($mulai, $sampai),
            'schools' => $this->dataSchools($mulai, $sampai),
            'parameters' => $this->dataParameters($mulai, $sampai),
        };

        $filename = "laporan-skrining-{$type}-{$mulai->format('Ymd')}-{$sampai->format('Ymd')}.csv";

        return response($this->toCsv($headers, $rows), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function dataRegions($mulai, $sampai): array
    {
        $rows = DashboardData::joinedScope($mulai, $sampai)
            ->selectRaw('sc.kab_kota as kab_kota')
            ->addSelect(DB::raw('count(e.id) as total_diuji'))
            ->addSelect(DB::raw('sum(case when '.DashboardData::reactiveExistsSql().' then 1 else 0 end) as jumlah_reaktif'))
            ->groupBy('sc.kab_kota')
            ->orderBy('sc.kab_kota')
            ->get();

        $data = $rows->map(function ($row) {
            $total = (int) $row->total_diuji;
            $reaktif = (int) $row->jumlah_reaktif;
            $persentase = $total > 0 ? $reaktif / $total * 100 : 0.0;

            return [
                $row->kab_kota,
                $total,
                $reaktif,
                number_format($persentase, 2, ',', '.'),
                DashboardData::tingkatReaktif($persentase),
            ];
        })->values();

        return [
            ['Kabupaten/Kota', 'Jumlah Diperiksa', 'Jumlah Reaktif', 'Persentase Reaktif (%)', 'Tingkat'],
            $data->all(),
        ];
    }

    private function dataSchools($mulai, $sampai): array
    {
        $rows = School::query()
            ->withCount(['examinations as jumlah_diuji' => fn (Builder $q) => $q->whereBetween('waktu_input', [$mulai, $sampai])])
            ->withCount(['examinations as jumlah_reaktif' => function (Builder $q) use ($mulai, $sampai) {
                $q->whereBetween('waktu_input', [$mulai, $sampai])
                    ->whereHas('examinationResults', fn (Builder $r) => $r->where('hasil', 'positif'));
            }])
            ->withMax(['examinations as tanggal_terakhir' => fn (Builder $q) => $q->whereBetween('waktu_input', [$mulai, $sampai])], 'waktu_input')
            ->orderByDesc('jumlah_reaktif')
            ->get();

        $data = $rows->map(function (School $school) {
            $total = (int) $school->jumlah_diuji;
            $reaktif = (int) $school->jumlah_reaktif;
            $persentase = $total > 0 ? $reaktif / $total * 100 : 0.0;

            return [
                $school->npsn,
                $school->nama,
                $school->kab_kota,
                $school->kecamatan,
                $school->jenjang,
                $school->target_siswa,
                $total,
                $reaktif,
                number_format($persentase, 2, ',', '.'),
                SchoolController::badgeTindakLanjut($persentase),
                $school->tanggal_terakhir ?? '',
            ];
        })->values();

        return [
            ['NPSN', 'Nama Sekolah', 'Kabupaten/Kota', 'Kecamatan', 'Jenjang', 'Target Siswa', 'Jumlah Diuji', 'Jumlah Reaktif', 'Persentase Reaktif (%)', 'Tindak Lanjut', 'Tanggal Skrining Terakhir'],
            $data->all(),
        ];
    }

    private function dataParameters($mulai, $sampai): array
    {
        $rows = DashboardData::joinedScope($mulai, $sampai)
            ->join('examination_results as er', 'er.examination_id', '=', 'e.id')
            ->where('er.hasil', 'positif')
            ->selectRaw('er.parameter as parameter')
            ->addSelect(DB::raw('count(*) as jumlah_reaktif'))
            ->groupBy('er.parameter')
            ->orderByDesc('jumlah_reaktif')
            ->get()
            ->keyBy('parameter');

        $data = collect(Examination::PARAMETERS)->map(function ($parameter) use ($rows) {
            return [
                $parameter,
                DashboardController::labelParameter($parameter),
                (int) ($rows[$parameter]->jumlah_reaktif ?? 0),
            ];
        })->sortByDesc(fn ($row) => $row[2])->values();

        return [
            ['Parameter', 'Keterangan', 'Jumlah Reaktif'],
            $data->all(),
        ];
    }

    private function toCsv(array $headers, array $rows): string
    {
        $out = "\xEF\xBB\xBF"; // BOM UTF-8 agar terbaca Excel
        $out .= implode(';', array_map(fn ($value) => $this->csvCell($value), $headers))."\r\n";

        foreach ($rows as $row) {
            $out .= implode(';', array_map(fn ($value) => $this->csvCell($value), array_values($row)))."\r\n";
        }

        return $out;
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        // Cegah CSV formula injection: nilai diawali =, +, -, @ dapat dieksekusi
        // sebagai formula oleh Excel/Spreadsheet saat file dibuka.
        if (in_array($value[0] ?? '', ['=', '+', '-', '@'], true)) {
            $value = "'" . $value;
        }

        return (str_contains($value, ';') || str_contains($value, '"') || str_contains($value, "\n"))
            ? '"'.str_replace('"', '""', $value).'"'
            : $value;
    }

    private function authorizeDinas(Request $request): void
    {
        abort_unless($request->user()->isDinas(), Response::HTTP_FORBIDDEN, 'Hanya role Dinas Provinsi yang diizinkan.');
    }
}
