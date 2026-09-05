<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_PETUGAS = 'petugas_lapangan';
    public const ROLE_PENGAWAS = 'pengawas_wilayah';
    public const ROLE_DINAS = 'dinas_provinsi';

    protected $fillable = [
        'nama',
        'email',
        'password',
        'role',
        'wilayah_scope',
        'avatar_initial',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isPetugas(): bool
    {
        return $this->role === self::ROLE_PETUGAS;
    }

    public function isPengawas(): bool
    {
        return $this->role === self::ROLE_PENGAWAS;
    }

    public function isDinas(): bool
    {
        return $this->role === self::ROLE_DINAS;
    }

    public function examinations(): HasMany
    {
        return $this->hasMany(Examination::class, 'petugas_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ScreeningSchedule::class, 'petugas_id');
    }

    public function getAvatarInitialAttribute(): ?string
    {
        // Hitung inisial dari nama bila belum diset, contoh "Rina Kusmawati" -> "RK"
        if ($this->attributes['avatar_initial'] ?? null) {
            return $this->attributes['avatar_initial'];
        }
        $words = preg_split('/\s+/', trim((string) $this->nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials !== '' ? $initials : null;
    }
}
