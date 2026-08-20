<?php

namespace App\Policies;

use App\Models\InternshipCertificate;
use App\Models\User;

class InternshipCertificatePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canRead($user);
    }

    public function view(User $user, InternshipCertificate $certificate): bool
    {
        return $this->canRead($user);
    }

    public function create(User $user): bool
    {
        return $this->isSdmDivisionHead($user);
    }

    public function update(User $user, InternshipCertificate $certificate): bool
    {
        return $this->isSdmDivisionHead($user);
    }

    public function delete(User $user, InternshipCertificate $certificate): bool
    {
        return false;
    }

    private function canRead(User $user): bool
    {
        return $user->is_active && (
            in_array($user->role?->slug, ['admin_surat', 'pimpinan'], true)
            || $this->isSdmDivisionHead($user)
        );
    }

    private function isSdmDivisionHead(User $user): bool
    {
        return $user->is_active
            && $user->role?->slug === 'ketua_divisi'
            && $user->division?->code === 'SDM';
    }
}
