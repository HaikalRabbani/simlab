<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningSchedule extends Model
{
    use HasFactory;

    public const SESI = ['pagi', 'siang'];
    public const STATUS = ['terjadwal', 'berlangsung', 'selesai'];

    protected $fillable = [
        'school_id',
        'petugas_id',
        'tanggal',
        'sesi',
        'target_siswa',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }
}
