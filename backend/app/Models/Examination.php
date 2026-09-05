<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Examination extends Model
{
    use HasFactory;

    public const PARAMETERS = ['THC', 'AMP', 'MET', 'MOP', 'BZO', 'TRA', 'ALKOHOL'];
    public const HASIL = ['negatif', 'positif', 'invalid'];
    public const RENCANA_TINDAK_LANJUT = ['rujuk_uji_konfirmasi', 'pantau_rutin', 'skrining_ulang'];
    public const STATUS_KIRIM = ['pending_sync', 'terkirim'];

    protected $fillable = [
        'client_uuid',
        'session_id',
        'schedule_id',
        'student_id',
        'petugas_id',
        'waktu_input',
        'pendamping',
        'catatan_petugas',
        'rencana_tindak_lanjut',
        'sampel_disegel',
        'kode_segel',
        'status_kirim',
        'is_locked',
        'strip_lot_code',
        'strip_expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'waktu_input' => 'datetime',
            'strip_expiry_date' => 'date',
            'sampel_disegel' => 'boolean',
            'is_locked' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ScreeningSchedule::class, 'schedule_id');
    }

    public function examinationResults(): HasMany
    {
        return $this->hasMany(ExaminationResult::class);
    }

    public function correctionRequests(): HasMany
    {
        return $this->hasMany(CorrectionRequest::class);
    }

    /** Sekolah tempat pemeriksaan berlangsung (via siswa). */
    public function school(): ?School
    {
        return $this->student?->school;
    }

    /** Apakah minimal satu parameter hasilnya positif. */
    public function hasReactiveResult(): bool
    {
        return $this->examinationResults()
            ->where('hasil', 'positif')
            ->exists();
    }

    /** Parameter yang reaktif (positif), misal untuk banner riwayat. */
    public function reactiveParameters(): array
    {
        return $this->examinationResults()
            ->where('hasil', 'positif')
            ->pluck('parameter')
            ->all();
    }
}
