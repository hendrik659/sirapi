<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingLetterHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'outgoing_letter_id',
        'activity',
        'notes',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'outgoing_letter_id' => 'integer',
            'changed_by' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function outgoingLetter(): BelongsTo
    {
        return $this->belongsTo(OutgoingLetter::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
