<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeDinas($request);

        $query = AuditLog::query()->with('user:id,nama,role');

        if ($request->filled('aksi')) {
            $query->where('aksi', $request->input('aksi'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('examination_id')) {
            $query->where('examination_id', $request->integer('examination_id'));
        }
        if ($request->filled('tanggal')) {
            $query->whereDate('created_at', $request->date('tanggal'));
        }

        return response()->json($query->orderByDesc('created_at')->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        $this->authorizeDinas($request);

        return response()->json($auditLog->load('user:id,nama,role'));
    }

    private function authorizeDinas(Request $request): void
    {
        abort_unless($request->user()->isDinas(), Response::HTTP_FORBIDDEN, 'Hanya role Dinas Provinsi yang diizinkan.');
    }
}
