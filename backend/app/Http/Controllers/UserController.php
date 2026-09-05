<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeDinas($request);

        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(fn ($q) => $q->where('nama', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        return response()->json($query->orderBy('nama')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeDinas($request);

        $data = $this->validateData($request);

        if (empty($data['password'])) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'Password wajib diisi saat membuat pengguna.');
        }

        $user = User::create($data);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'aksi' => 'create_user',
            'data_sesudah' => $user->toArray(),
        ]);

        return response()->json($user, Response::HTTP_CREATED);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorizeDinas($request);

        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorizeDinas($request);

        $data = $this->validateData($request, $user->id);
        $before = $user->toArray();
        $user->update($data);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'aksi' => 'update_user',
            'data_sebelum' => $before,
            'data_sesudah' => $user->toArray(),
        ]);

        return response()->json($user);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeDinas($request);

        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Tidak bisa menghapus akun sendiri.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $before = $user->toArray();
        $user->delete();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'aksi' => 'delete_user',
            'data_sebelum' => $before,
        ]);

        return response()->json(['message' => 'Pengguna dihapus.']);
    }

    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'nama' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($ignoreId)],
            'password' => 'nullable|string|min:8',
            'role' => ['required', Rule::in([
                User::ROLE_PETUGAS,
                User::ROLE_PENGAWAS,
                User::ROLE_DINAS,
            ])],
            'wilayah_scope' => 'nullable|string|max:255',
            'avatar_initial' => 'nullable|string|max:10',
        ]);

        if (in_array($data['role'], [User::ROLE_PETUGAS, User::ROLE_PENGAWAS], true) && empty($data['wilayah_scope'])) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, 'wilayah_scope wajib diisi untuk role petugas/pengawas.');
        }

        if ($data['role'] === User::ROLE_DINAS) {
            $data['wilayah_scope'] = null;
        }

        return $data;
    }

    private function authorizeDinas(Request $request): void
    {
        abort_unless($request->user()->isDinas(), Response::HTTP_FORBIDDEN, 'Hanya role Dinas Provinsi yang diizinkan.');
    }
}
