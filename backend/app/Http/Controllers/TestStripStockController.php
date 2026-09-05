<?php

namespace App\Http\Controllers;

use App\Models\Examination;
use App\Models\TestStripStock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class TestStripStockController extends Controller
{
    /**
     * Stok strip milik petugas yang login (modul "Stok Alat Uji", spec 5.1).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = TestStripStock::query()->with('school:id,npsn,nama');

        if ($user->isPetugas()) {
            $query->where('petugas_id', $user->id);
        }

        if ($request->filled('parameter')) {
            $query->where('parameter', $request->input('parameter'));
        }

        return response()->json($query->orderBy('parameter')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->authorizePetugas($request);

        $data = $request->validate($this->rules());
        $data['petugas_id'] = $user->id;

        return response()->json(TestStripStock::create($data), Response::HTTP_CREATED);
    }

    public function show(Request $request, TestStripStock $stock): JsonResponse
    {
        $user = $request->user();
        if ($user->isPetugas() && $stock->petugas_id !== $user->id) {
            return response()->json(['message' => 'Stok ini bukan milik Anda.'], Response::HTTP_FORBIDDEN);
        }

        return response()->json($stock->load('school:id,npsn,nama'));
    }

    public function update(Request $request, TestStripStock $stock): JsonResponse
    {
        $user = $request->user();
        $this->authorizePetugas($request);

        if ($stock->petugas_id !== $user->id) {
            return response()->json(['message' => 'Stok ini bukan milik Anda.'], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate($this->rules());
        $stock->update($data);

        return response()->json($stock);
    }

    public function destroy(Request $request, TestStripStock $stock): JsonResponse
    {
        $user = $request->user();
        $this->authorizePetugas($request);

        if ($stock->petugas_id !== $user->id) {
            return response()->json(['message' => 'Stok ini bukan milik Anda.'], Response::HTTP_FORBIDDEN);
        }

        $stock->delete();

        return response()->json(['message' => 'Catatan stok dihapus.']);
    }

    private function rules(): array
    {
        return [
            'school_id' => 'nullable|exists:schools,id',
            'parameter' => ['required', Rule::in(Examination::PARAMETERS)],
            'jumlah_stok' => 'required|integer|min:0',
            'lot_code' => 'required|string|max:64',
            'expiry_date' => 'required|date|after:today',
        ];
    }

    private function authorizePetugas(Request $request): void
    {
        abort_unless($request->user()->isPetugas(), Response::HTTP_FORBIDDEN, 'Hanya petugas lapangan yang mengelola stok.');
    }
}
