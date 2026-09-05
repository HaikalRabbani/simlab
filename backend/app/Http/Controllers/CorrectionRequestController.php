<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\CorrectionRequest;
use App\Models\Examination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorrectionRequestController extends Controller
{
    /**
     * Daftar pengajuan koreksi.
     * - petugas: pengajuannya sendiri
     * - pengawas: sekolah di wilayahnya (filter status=menunggu untuk panel kerja)
     * - dinas: tidak berhak melihat detail koreksi (berisi identitas siswa)
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isDinas()) {
            return response()->json(['message' => 'Role Dinas tidak mengakses detail koreksi.'], Response::HTTP_FORBIDDEN);
        }

        $query = CorrectionRequest::query()
            ->with(['examination.student:id,nisn,nama,kelas', 'diajukanOleh:id,nama']);

        if ($user->isPetugas()) {
            $query->where('diajukan_oleh', $user->id);
        } else {
            $query->whereHas('examination.student.school', fn (Builder $q) => $q->where('kab_kota', $user->wilayah_scope));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->orderByDesc('created_at')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'examination_id' => 'required|exists:examinations,id',
            'alasan' => 'required|string|max:1000',
        ]);

        $examination = Examination::findOrFail($data['examination_id']);

        if (! $user->isPetugas() || $examination->petugas_id !== $user->id) {
            return response()->json(['message' => 'Hanya petugas penginput yang bisa mengajukan koreksi.'], Response::HTTP_FORBIDDEN);
        }
        if (! $examination->is_locked) {
            return response()->json(['message' => 'Data belum terkunci, perbaiki langsung lewat update.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($examination->correctionRequests()->where('status', 'menunggu')->exists()) {
            return response()->json(['message' => 'Masih ada pengajuan koreksi yang menunggu.'], Response::HTTP_UNPROCESSABLE_ENTITY);
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

    public function show(Request $request, CorrectionRequest $correctionRequest): JsonResponse
    {
        $user = $request->user();

        $accessible = ($user->isPetugas() && $correctionRequest->diajukan_oleh === $user->id)
            || ($user->isPengawas() && $this->inWilayahPengawas($user, $correctionRequest));

        if (! $accessible) {
            return response()->json(['message' => 'Anda tidak berhak melihat pengajuan ini.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json($correctionRequest->load(['examination.student', 'examination.examinationResults', 'diajukanOleh:id,nama']));
    }

    /**
     * Persetujuan oleh pengawas wilayah tempat sekolah berada (spec 8.6 & 10).
     */
    public function approve(Request $request, CorrectionRequest $correctionRequest): JsonResponse
    {
        return $this->decide($request, $correctionRequest, 'disetujui');
    }

    public function reject(Request $request, CorrectionRequest $correctionRequest): JsonResponse
    {
        return $this->decide($request, $correctionRequest, 'ditolak');
    }

    public function update(): JsonResponse
    {
        return response()->json(['message' => 'Perubahan hanya lewat approve/reject.'], Response::HTTP_FORBIDDEN);
    }

    public function destroy(): JsonResponse
    {
        return response()->json(['message' => 'Pengajuan tidak bisa dihapus.'], Response::HTTP_FORBIDDEN);
    }

    private function decide(Request $request, CorrectionRequest $correction, string $status): JsonResponse
    {
        $user = $request->user();

        if (! $user->isPengawas() || ! $this->inWilayahPengawas($user, $correction)) {
            return response()->json(['message' => 'Hanya pengawas wilayah sekolah tersebut yang berhak.'], Response::HTTP_FORBIDDEN);
        }
        if ($correction->status !== 'menunggu') {
            return response()->json(['message' => 'Pengajuan ini sudah diputuskan.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $request->validate([
            'catatan_peninjau' => 'nullable|string|max:1000',
        ]);

        $correction->update([
            'status' => $status,
            'ditinjau_oleh' => $user->id,
            'catatan_peninjau' => $data['catatan_peninjau'] ?? null,
        ]);

        // Bila disetujui: buka kunci pemeriksaan supaya pengawas wilayah bisa
        // memperbaiki datanya (petugas tidak bisa mengedit data terkirim — spec
        // 8.6). Setelah perbaikan, ExaminationController::update mengunci kembali.
        if ($status === 'disetujui') {
            $correction->examination()->update(['is_locked' => false]);
        }

        AuditLog::create([
            'user_id' => $user->id,
            'examination_id' => $correction->examination_id,
            'aksi' => $status === 'disetujui' ? 'approve_correction' : 'reject_correction',
            'data_sebelum' => ['status' => 'menunggu'],
            'data_sesudah' => $correction->toArray(),
        ]);

        return response()->json($correction);
    }

    private function inWilayahPengawas($user, CorrectionRequest $correction): bool
    {
        $kabKota = $correction->examination?->student?->school?->kab_kota;

        return $kabKota !== null && $kabKota === $user->wilayah_scope;
    }
}
