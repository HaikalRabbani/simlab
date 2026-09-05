<?php

namespace App\Http\Controllers;

use App\Models\Examination;
use App\Models\School;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SchoolController extends Controller
{
    /**
     * Daftar sekolah di-scope role:
     * - petugas: sekolah pada jadwalnya
     * - pengawas: sekolah di kab/kota wilayahnya
     * - dinas: seluruh provinsi (dengan filter)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = School::query();

        if ($user->isPetugas()) {
            $query->whereHas('schedules', fn (Builder $q) => $q->where('petugas_id', $user->id));
        } elseif ($user->isPengawas()) {
            $query->where('kab_kota', $user->wilayah_scope);
        }

        if ($request->filled('kab_kota')) {
            $query->where('kab_kota', $request->input('kab_kota'));
        }
        if ($request->filled('jenjang')) {
            $query->where('jenjang', $request->input('jenjang'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(fn (Builder $q) => $q->where('nama', 'like', "%{$search}%")
                ->orWhere('npsn', 'like', "%{$search}%"));
        }

        $schools = $query->orderBy('nama')->paginate($request->integer('per_page', 20))->withQueryString();

        return response()->json($schools);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeDinas($request);

        $data = $request->validate($this->rules());

        return response()->json(School::create($data), Response::HTTP_CREATED);
    }

    public function show(School $school): JsonResponse
    {
        return response()->json($school->loadCount('students'));
    }

    public function update(Request $request, School $school): JsonResponse
    {
        $this->authorizeDinas($request);

        $data = $request->validate($this->rules($school->id));
        $school->update($data);

        return response()->json($school);
    }

    public function destroy(Request $request, School $school): JsonResponse
    {
        $this->authorizeDinas($request);

        $school->delete();

        return response()->json(['message' => 'Sekolah dihapus.']);
    }

    /**
     * Daftar Sekolah (Dinas) — status skrining per sekolah + rekap (spec 6.5 & 8.9).
     */
    public function status(Request $request): JsonResponse
    {
        $this->authorizeDinas($request);

        $bulanIni = now()->format('Y-m');

        $query = School::query()
            ->withCount(['students'])
            ->withCount('examinations as jumlah_diuji')
            ->withCount(['examinations as jumlah_reaktif' => function (Builder $q) {
                $q->whereHas('examinationResults', fn (Builder $r) => $r->where('hasil', 'positif'));
            }])
            ->withCount(['schedules as jadwal_bulan_ini' => function (Builder $q) use ($bulanIni) {
                $q->where('tanggal', 'like', "{$bulanIni}-%");
            }])
            ->withMax('examinations as tanggal_skrining_terakhir', 'waktu_input');

        if ($request->filled('kab_kota')) {
            $query->where('kab_kota', $request->input('kab_kota'));
        }
        if ($request->filled('jenjang')) {
            $query->where('jenjang', $request->input('jenjang'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(fn (Builder $q) => $q->where('nama', 'like', "%{$search}%")
                ->orWhere('npsn', 'like', "%{$search}%"));
        }

        $schools = $query->orderByDesc('jumlah_reaktif')->get();

        $rows = $schools->map(function (School $school) {
            $jumlahDiuji = (int) $school->jumlah_diuji;
            $jumlahReaktif = (int) $school->jumlah_reaktif;
            $persentase = $jumlahDiuji > 0 ? round($jumlahReaktif / $jumlahDiuji * 100, 2) : 0.0;
            $sudahDiskrining = $jumlahDiuji > 0;

            return [
                'id' => $school->id,
                'npsn' => $school->npsn,
                'nama' => $school->nama,
                'kab_kota' => $school->kab_kota,
                'kecamatan' => $school->kecamatan,
                'jenjang' => $school->jenjang,
                'target_siswa' => $school->target_siswa,
                'jumlah_siswa' => (int) $school->students_count,
                'tanggal_skrining_terakhir' => $school->tanggal_skrining_terakhir,
                'jumlah_diuji' => $jumlahDiuji,
                'jumlah_reaktif' => $jumlahReaktif,
                'persentase_reaktif' => $persentase,
                'terjadwal_bulan_ini' => (int) $school->jadwal_bulan_ini > 0,
                'status_skrining' => $sudahDiskrining ? 'sudah_diskrining' : ($school->jadwal_bulan_ini > 0 ? 'terjadwal' : 'belum_terjadwal'),
                'tindak_lanjut' => self::badgeTindakLanjut($persentase),
            ];
        })->values();

        $summary = [
            'total_sekolah' => $schools->count(),
            'sudah_diskrining' => $rows->where('status_skrining', 'sudah_diskrining')->count(),
            'terjadwal_bulan_ini' => $rows->where('terjadwal_bulan_ini', true)->count(),
            'belum_terjadwal' => $rows->where('status_skrining', 'belum_terjadwal')->count(),
            'temuan_di_atas_2' => $rows->where('persentase_reaktif', '>', 2)->count(),
        ];

        return response()->json(['summary' => $summary, 'schools' => $rows]);
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'npsn' => ['required', 'string', 'max:20', Rule::unique('schools', 'npsn')->ignore($ignoreId)],
            'nama' => 'required|string|max:255',
            'kab_kota' => 'required|string|max:255',
            'kecamatan' => 'required|string|max:255',
            'wilayah_group' => 'nullable|string|in:Bandung Raya,Bodebek,Pantura,Priangan Timur',
            'jenjang' => ['required', Rule::in(School::JENJANG)],
            'target_siswa' => 'required|integer|min:1',
        ];
    }

    public static function badgeTindakLanjut(float $persentase): string
    {
        if ($persentase > 2.3) {
            return 'Pendampingan';
        }
        if ($persentase >= 1.7) {
            return 'Skrining ulang';
        }
        if ($persentase >= 1.0) {
            return 'Pantau rutin';
        }

        return 'Selesai';
    }

    private function authorizeDinas(Request $request): void
    {
        abort_unless($request->user()->isDinas(), Response::HTTP_FORBIDDEN, 'Hanya role Dinas Provinsi yang diizinkan.');
    }
}
