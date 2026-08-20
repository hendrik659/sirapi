<?php

namespace App\Policies;

use App\Models\OutgoingLetter;
use App\Models\User;

class OutgoingLetterPolicy
{
    /**
     * @var array<int, string>
     */
    private const EDITOR_ROLES = [
        'ketua_divisi',
        'anggota_divisi',
    ];

    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, OutgoingLetter $outgoingLetter): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $this->isDivisionEditor($user);
    }

    private function isDivisionEditor(User $user): bool
    {
        return $user->is_active
            && $user->division_id !== null
            && in_array($user->role?->slug, self::EDITOR_ROLES, true);
    }
}
