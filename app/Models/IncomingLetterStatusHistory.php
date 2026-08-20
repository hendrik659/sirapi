<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomingLetterStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'incoming_letter_id',
        'previous_status',
        'new_status',
        'activity',
        'notes',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'incoming_letter_id' => 'integer',
            'changed_by' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function incomingLetter(): BelongsTo
    {
        return $this->belongsTo(IncomingLetter::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
