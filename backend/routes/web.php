<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'app' => 'SIMLAB Jabar API',
        'message' => 'SIMLAB Jabar — Sistem Informasi Skrining Kesehatan Siswa',
    ]);
});
