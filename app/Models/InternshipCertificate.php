<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternshipCertificate extends Model
{
    protected $fillable = [
        'archive_code',
        'participant_name',
        'institution_name',
        'major_name',
        'start_date',
        'end_date',
        'document_path',
        'original_document_name',
        'document_mime_type',
        'document_size',
        'created_by',
    ];

    protected $hidden = [
        'document_path',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'document_size' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
