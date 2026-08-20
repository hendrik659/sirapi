<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutgoingLetter extends Model
{
    protected $fillable = [
        'reference_code',
        'letter_number',
        'letter_date',
        'recipient_name',
        'recipient_address',
        'subject',
        'division_id',
        'created_by',
        'document_path',
        'original_document_name',
        'document_mime_type',
        'document_size',
    ];

    protected $hidden = [
        'document_path',
    ];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'division_id' => 'integer',
            'created_by' => 'integer',
            'document_size' => 'integer',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OutgoingLetterHistory::class)
            ->latest('created_at')
            ->latest('id');
    }
}
