<?php

namespace App\Http\Controllers;

use App\Models\ScreeningSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ScreeningScheduleController extends Controller
{
    /**
     * Jadwal kunjungan. Petugas hanya melihat jadwal miliknya;
     * pengawas melihat sekolah di wilayahnya; dinas melihat semua.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ScreeningSchedule::query()
            ->with(['school:id,npsn,nama,kab_kota,kecamatan', 'petugas:id,nama']);

        if ($user->isPetugas()) {
            $petugasId = $request->integer('petugas_id', $user->id);
            if ($petugasId !== $user->id) {
                return response()->json(['message' => 'Petugas hanya bisa melihat jadwal sendiri.'], Response::HTTP_FORBIDDEN);
            }
            $query->where('petugas_id', $user->id);
        } elseif ($user->isPengawas()) {
            $query->whereHas('school', fn (Builder $q) => $q->where('kab_kota', $user->wilayah_scope));
        } elseif ($request->filled('petugas_id')) {
            $query->where('petugas_id', $request->integer('petugas_id'));
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('tanggal', $request->date('tanggal'));
        }
        if ($request->filled('sesi')) {
            $query->where('sesi', $request->input('sesi'));
        }
        if ($request->filled('school_id')) {
            $query->where('school_id', $request->integer('school_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $schedules = $query->orderBy('tanggal')->orderBy('sesi')
            ->paginate($request->integer('per_page', 20))->withQueryString();

        return response()->json($schedules);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeDinas($request);

        $data = $this->validateData($request);
        $data['status'] = $data['status'] ?? 'terjadwal';
        $schedule = ScreeningSchedule::create($data);

        return response()->json($schedule->load(['school:id,npsn,nama', 'petugas:id,nama']), Response::HTTP_CREATED);
    }

    public function show(Request $request, ScreeningSchedule $schedule): JsonResponse
    {
        $user = $request->user();

        $accessible = $user->isDinas()
            || ($user->isPetugas() && $schedule->petugas_id === $user->id)
            || ($user->isPengawas() && $schedule->school?->kab_kota === $user->wilayah_scope);

        if (! $accessible) {
            return response()->json(['message' => 'Anda tidak berhak melihat jadwal ini.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json($schedule->load(['school', 'petugas:id,nama']));
    }

    public function update(Request $request, ScreeningSchedule $schedule): JsonResponse
    {
        $this->authorizeDinas($request);

        $data = $this->validateData($request, $schedule->id);
        $schedule->update($data);

        return response()->json($schedule->load(['school:id,npsn,nama', 'petugas:id,nama']));
    }

    public function destroy(Request $request, ScreeningSchedule $schedule): JsonResponse
    {
        $this->authorizeDinas($request);

        $schedule->delete();

        return response()->json(['message' => 'Jadwal dihapus.']);
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'school_id' => 'required|exists:schools,id',
            'petugas_id' => ['required', 'exists:users,id', function (string $attribute, $value, $fail) {
                $user = User::find($value);
                if ($user && ! $user->isPetugas()) {
                    $fail('petugas_id harus mengarah ke user ber-role petugas_lapangan.');
                }
            }],
            'tanggal' => 'required|date',
            'sesi' => ['required', Rule::in(ScreeningSchedule::SESI)],
            'target_siswa' => 'required|integer|min:1',
            'status' => ['nullable', Rule::in(ScreeningSchedule::STATUS)],
        ]);
    }

    private function authorizeDinas(Request $request): void
    {
        abort_unless($request->user()->isDinas(), Response::HTTP_FORBIDDEN, 'Hanya role Dinas Provinsi yang diizinkan.');
    }
}
