<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class School extends Model
{
    use HasFactory;

    public const JENJANG = ['SMA', 'SMK', 'MA'];

    protected $fillable = [
        'npsn',
        'nama',
        'kab_kota',
        'kecamatan',
        'wilayah_group',
        'jenjang',
        'target_siswa',
    ];

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ScreeningSchedule::class);
    }

    public function examinations(): HasManyThrough
    {
        return $this->hasManyThrough(
            Examination::class,
            Student::class,
            'school_id',   // FK di students
            'student_id',  // FK di examinations
            'id',          // local key schools
            'id'           // local key students
        );
    }
}
