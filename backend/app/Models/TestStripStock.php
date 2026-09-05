<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestStripStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'petugas_id',
        'school_id',
        'parameter',
        'jumlah_stok',
        'lot_code',
        'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'expiry_date' => 'date',
            'jumlah_stok' => 'integer',
        ];
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
