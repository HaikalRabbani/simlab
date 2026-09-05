<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExaminationRequest;
use App\Models\AuditLog;
use App\Models\CorrectionRequest;
use App\Models\Examination;
use App\Models\ExaminationResult;
use App\Models\ScreeningSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ExaminationController extends Controller
{
    /**
     * Riwayat pemeriksaan — di-scope per role.
     * Petugas: miliknya sendiri (filter petugas_id & tanggal).
     * Pengawas: wilayahnya. Dinas: tidak boleh (hanya agregat).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isDinas()) {
            return response()->json([
                'message' => 'Role Dinas Provinsi hanya menerima data agregat, bukan daftar pemeriksaan.',
            ], Response::HTTP_FORBIDDEN);
        }

        $query = Examination::query()
            ->with(['student.school:id,npsn,nama,kab_kota,kecamatan', 'petugas:id,nama', 'examinationResults']);

        if ($user->isPetugas()) {
            $petugasId = $request->integer('petugas_id', $user->id);
            if ($petugasId !== $user->id) {
                return response()->json(['message' => 'Petugas hanya bisa melihat data sendiri.'], Response::HTTP_FORBIDDEN);
            }
            $query->where('petugas_id', $user->id);
        } else { // pengawas_wilayah
            $query->whereHas('student.school', function (Builder $q) use ($user) {
                $q->where('kab_kota', $user->wilayah_scope);
            });
        }

        if ($request->filled('tanggal')) {
            $query->whereDate('waktu_input', $request->date('tanggal'));
        }
        if ($request->filled('status_kirim')) {
            $query->where('status_kirim', $request->input('status_kirim'));
        }
        if ($request->filled('schedule_id')) {
            $query->where('schedule_id', $request->integer('schedule_id'));
        }

        $examinations = $query->orderByDesc('waktu_input')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return response()->json($examinations->through(fn (Examination $e) => $this->serialize($e)));
    }

    /**
     * Submit pemeriksaan baru. Idempoten via client_uuid; begitu diterima server
     * langsung status_kirim=terkirim & is_locked=true (spec 8.6, 9.2, 9.6).
     */
    public function store(StoreExaminationRequest $request): JsonResponse
    {
        $this->authorize('create', Examination::class);
        $user = $request->user();

        // Idempotency: retry dari device offline tidak boleh membuat data dobel
        if ($request->filled('client_uuid')) {
            $existing = Examination::where('client_uuid', $request->input('client_uuid'))->first();
            if ($existing) {
                return response()->json($this->serialize($existing->load(['student.school', 'examinationResults'])), Response::HTTP_OK);
            }
        }

        $student = \App\Models\Student::with('school')->findOrFail($request->integer('student_id'));

        $this->ensurePetugasScheduledFor($student, $request);

        $waktuInput = $request->filled('waktu_input')
            ? $request->date('waktu_input')
            : now();

        $examination = DB::transaction(function () use ($request, $user, $student, $waktuInput) {
            $examination = Examination::create([
                'client_uuid' => $request->input('client_uuid'),
                'schedule_id' => $request->input('schedule_id'),
                'student_id' => $student->id,
                'petugas_id' => $user->id,
                'waktu_input' => $waktuInput,
                'pendamping' => $request->input('pendamping'),
                'catatan_petugas' => $request->input('catatan_petugas'),
                'strip_lot_code' => $request->input('strip_lot_code'),
                'strip_expiry_date' => $request->date('strip_expiry_date'),
                'rencana_tindak_lanjut' => $request->input('rencana_tindak_lanjut'),
                'sampel_disegel' => $request->boolean('sampel_disegel'),
                'kode_segel' => $request->input('kode_segel'),
                'status_kirim' => 'terkirim',
                'is_locked' => true,
            ]);

            foreach ($request->input('hasil') as $item) {
                ExaminationResult::create([
                    'examination_id' => $examination->id,
                    'parameter' => $item['parameter'],
                    'hasil' => $item['hasil'],
                ]);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'examination_id' => $examination->id,
                'aksi' => 'submit_examination',
                'data_sebelum' => null,
                'data_sesudah' => $examination->load('examinationResults')->toArray(),
            ]);

            return $examination;
        });

        return response()->json(
            $this->serialize($examination->load(['student.school', 'examinationResults'])),
            Response::HTTP_CREATED
        );
    }

    public function show(Request $request, Examination $examination): JsonResponse
    {
        $this->authorize('view', $examination);

        $examination->load(['student.school', 'petugas:id,nama', 'examinationResults', 'correctionRequests']);

        return response()->json($this->serialize($examination));
    }

    /**
     * Edit data yang belum terkunci (status pending). Data terkirim immutable —
     * perbaikan lewat alur correction_requests.
     */
    public function update(StoreExaminationRequest $request, Examination $examination): JsonResponse
    {
        $this->authorize('update', $examination);

        $updated = DB::transaction(function () use ($request, $examination) {
            $before = $examination->load('examinationResults')->toArray();

            $examination->update([
                'pendamping' => $request->input('pendamping'),
                'catatan_petugas' => $request->input('catatan_petugas'),
                'rencana_tindak_lanjut' => $request->input('rencana_tindak_lanjut'),
                'sampel_disegel' => $request->boolean('sampel_disegel'),
                'kode_segel' => $request->input('kode_segel'),
            ]);

            $examination->examinationResults()->delete();
            foreach ($request->input('hasil') as $item) {
                ExaminationResult::create([
                    'examination_id' => $examination->id,
                    'parameter' => $item['parameter'],
                    'hasil' => $item['hasil'],
                ]);
            }

            // Data yang diperbaiki lewat alur koreksi yang disetujui dikunci
            // kembali — data final bersifat immutable (spec 8.6).
            if ($examination->correctionRequests()->where('status', 'disetujui')->exists()) {
                $examination->update(['is_locked' => true]);
            }

            AuditLog::create([
                'user_id' => $request->user()->id,
                'examination_id' => $examination->id,
                'aksi' => 'update_examination',
                'data_sebelum' => $before,
                'data_sesudah' => $examination->load('examinationResults')->toArray(),
            ]);

            return $examination;
        });

        return response()->json($this->serialize($updated->load('examinationResults')));
    }

    public function destroy(Examination $examination): JsonResponse
    {
        $this->authorize('delete', $examination);

        return response()->json(['message' => 'Penghapusan data pemeriksaan tidak diizinkan.'], Response::HTTP_FORBIDDEN);
    }

    /**
     * Pengajuan koreksi oleh petugas untuk data yang sudah terkirim (spec 8.6).
     */
    public function requestCorrection(Request $request, Examination $examination): JsonResponse
    {
        $user = $request->user();

        if (! $user->isPetugas() || $examination->petugas_id !== $user->id) {
            return response()->json(['message' => 'Hanya petugas penginput yang bisa mengajukan koreksi.'], Response::HTTP_FORBIDDEN);
        }

        if (! $examination->is_locked) {
            return response()->json(['message' => 'Data belum terkunci, silakan perbaiki langsung.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'alasan' => 'required|string|max:1000',
        ]);

        $openRequest = CorrectionRequest::where('examination_id', $examination->id)
            ->where('status', 'menunggu')
            ->exists();

        if ($openRequest) {
            return response()->json(['message' => 'Masih ada pengajuan koreksi yang menunggu untuk pemeriksaan ini.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $correction = CorrectionRequest::create([
            'examination_id' => $examination->id,
            'diajukan_oleh' => $user->id,
            'alasan' => $data['alasan'],
            'status' => 'menunggu',
        ]);

        AuditLog::create([
            'user_id' => $user->id,
            'examination_id' => $examination->id,
            'aksi' => 'request_correction',
            'data_sesudah' => $correction->toArray(),
        ]);

        return response()->json($correction, Response::HTTP_CREATED);
    }

    private function ensurePetugasScheduledFor(\App\Models\Student $student, Request $request): void
    {
        $scheduleId = $request->input('schedule_id');
        $tanggal = $request->filled('waktu_input')
            ? $request->date('waktu_input')->toDateString()
            : now()->toDateString();

        $query = ScreeningSchedule::query()
            ->where('petugas_id', $request->user()->id)
            ->where('school_id', $student->school_id);

        if ($scheduleId) {
            $schedule = $query->where('id', $scheduleId)->first();
            if (! $schedule || $schedule->tanggal->toDateString() !== $tanggal) {
                throw ValidationException::withMessages([
                    'schedule_id' => 'Jadwal bukan milik petugas ini atau tanggalnya tidak sesuai.',
                ]);
            }

            return;
        }

        $scheduled = $query->whereDate('tanggal', $tanggal)->exists();
        if (! $scheduled) {
            throw ValidationException::withMessages([
                'student_id' => "Sekolah {$student->school?->nama} tidak terjadwal untuk petugas ini pada {$tanggal}.",
            ]);
        }
    }

    /**
     * Ringkasan hasil untuk baris Riwayat Input (spec 6.2).
     */
    private function serialize(Examination $e): array
    {
        $results = $e->examinationResults;
        $reactive = $results->where('hasil', 'positif')->pluck('parameter')->values();
        $hasInvalid = $results->contains('hasil', 'invalid');

        $ringkasan = $reactive->isNotEmpty()
            ? $reactive->implode(', ').' reaktif'
            : ($hasInvalid ? 'Invalid — diulang' : 'Negatif semua');

        return [
            'id' => $e->id,
            'client_uuid' => $e->client_uuid,
            'schedule_id' => $e->schedule_id,
            'student' => $e->relationLoaded('student') && $e->student ? [
                'id' => $e->student->id,
                'nisn' => $e->student->nisn,
                'nama' => $e->student->nama,
                'kelas' => $e->student->kelas,
                'jenis_kelamin' => $e->student->jenis_kelamin,
                'school' => $e->student->school ? [
                    'id' => $e->student->school->id,
                    'npsn' => $e->student->school->npsn,
                    'nama' => $e->student->school->nama,
                    'kab_kota' => $e->student->school->kab_kota,
                    'kecamatan' => $e->student->school->kecamatan,
                ] : null,
            ] : null,
            'petugas_id' => $e->petugas_id,
            'waktu_input' => $e->waktu_input?->toISOString(),
            'pendamping' => $e->pendamping,
            'catatan_petugas' => $e->catatan_petugas,
            'rencana_tindak_lanjut' => $e->rencana_tindak_lanjut,
            'sampel_disegel' => $e->sampel_disegel,
            'kode_segel' => $e->kode_segel,
            'strip_lot_code' => $e->strip_lot_code,
            'strip_expiry_date' => $e->strip_expiry_date?->toDateString(),
            'status_kirim' => $e->status_kirim,
            'is_locked' => $e->is_locked,
            'reactive_parameters' => $reactive,
            'hasil_ringkasan' => $ringkasan,
            'results' => $results->map(fn (ExaminationResult $r) => [
                'parameter' => $r->parameter,
                'hasil' => $r->hasil,
            ])->values(),
        ];
    }
}
