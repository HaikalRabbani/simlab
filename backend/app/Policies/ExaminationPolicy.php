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
     * Pengawas wilayah boleh memperbaiki data bila ada koreksi yang sudah disetujui
     * dan pemeriksaan sedang terbuka (dibuka oleh approve) — spec 8.6.
     */
    public function update(User $user, Examination $examination): bool
    {
        if ($user->isPetugas()) {
            return $examination->petugas_id === $user->id && ! $examination->is_locked;
        }

        if ($user->isPengawas()) {
            $school = $examination->school();

            return $school !== null
                && $school->kab_kota === $user->wilayah_scope
                && ! $examination->is_locked
                && $examination->correctionRequests()->where('status', 'disetujui')->exists();
        }

        return false;
    }

    /**
     * Data yang sudah terkirim tidak boleh dihapus — koreksi lewat correction_requests.
     */
    public function delete(User $user, Examination $examination): bool
    {
        return false;
    }
}
