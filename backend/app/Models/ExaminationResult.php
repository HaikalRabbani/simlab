<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaminationResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'examination_id',
        'parameter',
        'hasil',
    ];

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }
}
