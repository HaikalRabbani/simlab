<?php

namespace App\Policies;

use App\Models\Examination;
use App\Models\User;

class ExaminationPolicy
{
    /**
     * Identitas siswa + hasil hanya untuk petugas penginput dan pengawas wilayah sekolah tsb.
     * Dinas provinsi TIDAK boleh melihat detail pemeriksaan (hanya agregat).
     */
    public function view(User $user, Examination $examination): bool
    {
        if ($user->isDinas()) {
            return false;
        }

        if ($user->isPetugas()) {
            return $examination->petugas_id === $user->id;
        }

        // Pengawas wilayah: scope = kab/kota tempat sekolah berada
        $school = $examination->school();

        return $user->isPengawas()
            && $school !== null
            && $school->kab_kota === $user->wilayah_scope;
    }

    public function create(User $user): bool
    {
        return $user->isPetugas();
    }

    /**
     * Hanya petugas penginput, dan hanya bila data belum terkunci (belum terkirim final).
     */
    public function update(User $user, Examination $examination): bool
    {
        return $user->isPetugas()
            && $examination->petugas_id === $user->id
            && ! $examination->is_locked;
    }

    /**
     * Data yang sudah terkirim tidak boleh dihapus — koreksi lewat correction_requests.
     */
    public function delete(User $user, Examination $examination): bool
    {
        return false;
    }
}
