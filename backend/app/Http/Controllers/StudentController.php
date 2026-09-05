<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class StudentController extends Controller
{
    /**
     * Lookup siswa per sekolah untuk auto-fill NISN (spec 6.1 Langkah 2).
     * Dinas Provinsi tidak boleh melihat identitas siswa.
     */
    public function lookup(Request $request, School $school): JsonResponse
    {
        $user = $request->user();

        if ($user->isDinas()) {
            return $this->forbiddenDinas();
        }

        if ($user->isPengawas() && $school->kab_kota !== $user->wilayah_scope) {
            return response()->json(['message' => 'Sekolah di luar wilayah tugas Anda.'], Response::HTTP_FORBIDDEN);
        }

        if ($user->isPetugas() && ! $this->schoolScheduledForPetugas($school, $user->id)) {
            return response()->json(['message' => 'Sekolah tidak terjadwal untuk Anda.'], Response::HTTP_FORBIDDEN);
        }

        $query = Student::query()->where('school_id', $school->id);

        if ($request->filled('nisn')) {
            $query->where('nisn', $request->input('nisn'));
        } elseif ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(fn (Builder $q) => $q->where('nama', 'like', "%{$search}%")
                ->orWhere('nisn', 'like', "%{$search}%"));
        }

        $students = $query->orderBy('nama')->limit(50)->get();

        return response()->json($students);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isDinas()) {
            return $this->forbiddenDinas();
        }

        $query = Student::query()->with('school:id,npsn,nama,kab_kota');

        if ($user->isPetugas()) {
            // Petugas wajib menyebut school_id milik sekolah pada jadwalnya
            $schoolId = $request->integer('school_id');
            if (! $schoolId) {
                return response()->json(['message' => 'Parameter school_id wajib diisi petugas.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $school = School::find($schoolId);
            if (! $school || ! $this->schoolScheduledForPetugas($school, $user->id)) {
                return response()->json(['message' => 'Sekolah tidak terjadwal untuk Anda.'], Response::HTTP_FORBIDDEN);
            }
            $query->where('school_id', $schoolId);
        } else {
            $query->whereHas('school', fn (Builder $q) => $q->where('kab_kota', $user->wilayah_scope));
        }

        if ($request->filled('nisn')) {
            $query->where('nisn', $request->input('nisn'));
        }

        $students = $query->orderBy('nama')->paginate($request->integer('per_page', 50))->withQueryString();

        return response()->json($students);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isPetugas()) {
            return response()->json(['message' => 'Hanya petugas lapangan yang bisa menambah data siswa.'], Response::HTTP_FORBIDDEN);
        }

        $school = School::findOrFail((int) $request->input('school_id'));
        if (! $this->schoolScheduledForPetugas($school, $user->id)) {
            return response()->json(['message' => 'Sekolah tidak terjadwal untuk Anda.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate($this->rules());

        return response()->json(Student::create($data), Response::HTTP_CREATED);
    }

    public function show(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if ($user->isDinas() || ! $this->canAccessStudent($user, $student)) {
            return response()->json(['message' => 'Anda tidak berhak melihat data siswa ini.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json($student->load('school:id,npsn,nama,kab_kota,kecamatan'));
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        if (! $this->canAccessStudent($user, $student)) {
            return response()->json(['message' => 'Anda tidak berhak mengubah data siswa ini.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate($this->rules($student->id));
        $student->update($data);

        return response()->json($student);
    }

    public function destroy(Request $request, Student $student): JsonResponse
    {
        $user = $request->user();

        // Hanya petugas di sekolah jadwalnya.
        if (! $user->isPetugas() || ! $this->canAccessStudent($user, $student)) {
            return response()->json(['message' => 'Anda tidak berhak menghapus data siswa ini.'], Response::HTTP_FORBIDDEN);
        }

        // Keamanan data (spec 8.6 & 10): siswa yang pernah diperiksa tidak boleh
        // dihapus — penghapusan akan meng-cascade menghapus examination yang sudah
        // terkirim/terkunci dan merusak jejak audit.
        if ($student->examinations()->exists()) {
            return response()->json([
                'message' => 'Siswa ini memiliki riwayat pemeriksaan dan tidak dapat dihapus. Data yang sudah terkirim bersifat permanen.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $student->delete();

        return response()->json(['message' => 'Data siswa dihapus.']);
    }

    private function rules(?int $ignoreId = null): array
    {
        return [
            'school_id' => 'required|exists:schools,id',
            'nisn' => ['required', 'string', 'max:20', Rule::unique('students', 'nisn')->ignore($ignoreId)],
            'nama' => 'required|string|max:255',
            'kelas' => 'required|string|max:50',
            'jenis_kelamin' => 'required|in:L,P',
            'tanggal_lahir' => 'required|date|before:today',
        ];
    }

    private function schoolScheduledForPetugas(School $school, int $petugasId): bool
    {
        return $school->schedules()->where('petugas_id', $petugasId)->exists();
    }

    private function canAccessStudent($user, Student $student): bool
    {
        if ($user->isPetugas()) {
            return $this->schoolScheduledForPetugas($student->school, $user->id);
        }

        return $user->isPengawas() && $student->school?->kab_kota === $user->wilayah_scope;
    }

    private function forbiddenDinas(): JsonResponse
    {
        return response()->json([
            'message' => 'Role Dinas Provinsi tidak mengakses identitas siswa — gunakan endpoint agregat.',
        ], Response::HTTP_FORBIDDEN);
    }
}
