<?php

namespace App\Policies;

use App\Models\IncomingLetter;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class IncomingLetterPolicy
{
    public function review(User $user, IncomingLetter $incomingLetter): Response
    {
        $isPimpinan = $user->role?->slug === 'pimpinan';
        $isSdmDivisionHead = $user->role?->slug === 'ketua_divisi'
            && $user->division?->code === 'SDM';

        if (! $isPimpinan && ! $isSdmDivisionHead) {
            return Response::deny('Anda tidak berhak memeriksa surat masuk.');
        }

        if ($incomingLetter->status !== IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN) {
            return Response::deny('Surat masuk tidak berada pada status menunggu pemeriksaan.');
        }

        if ($incomingLetter->review()->exists()) {
            return Response::deny('Surat masuk sudah diperiksa.');
        }

        return Response::allow();
    }
}
