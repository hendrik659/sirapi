<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomingLetterReview extends Model
{
    protected $fillable = [
        'incoming_letter_id',
        'reviewed_by',
        'destination_division_id',
        'review_note',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'incoming_letter_id' => 'integer',
            'reviewed_by' => 'integer',
            'destination_division_id' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function incomingLetter(): BelongsTo
    {
        return $this->belongsTo(IncomingLetter::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function destinationDivision(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'destination_division_id');
    }
}
