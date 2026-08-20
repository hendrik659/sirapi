<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IncomingLetter extends Model
{
    public const STATUS_BARU_DITERIMA = 'baru_diterima';

    public const STATUS_MENUNGGU_PEMERIKSAAN = 'menunggu_pemeriksaan';

    public const STATUS_SELESAI = 'selesai';

    protected $fillable = [
        'agenda_number',
        'letter_number',
        'sender_name',
        'addressed_to',
        'letter_date',
        'received_date',
        'received_via',
        'subject',
        'summary',
        'priority',
        'destination_division_id',
        'document_path',
        'original_document_name',
        'document_mime_type',
        'document_size',
        'status',
        'created_by',
        'submitted_for_review_at',
    ];

    protected $hidden = [
        'document_path',
    ];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'received_date' => 'date',
            'document_size' => 'integer',
            'submitted_for_review_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function destinationDivision(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'destination_division_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(IncomingLetterReview::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(IncomingLetterStatusHistory::class)
            ->latest('created_at')
            ->latest('id');
    }
}
