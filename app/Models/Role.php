<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    private const DISPLAY_NAMES = [
        'admin_surat' => 'Admin',
        'pimpinan' => 'Pimpinan',
        'ketua_divisi' => 'Ketua Divisi',
        'anggota_divisi' => 'Anggota Divisi',
    ];

    protected $fillable = [
        'name',
        'slug',
    ];

    public function getDisplayNameAttribute(): string
    {
        return self::DISPLAY_NAMES[$this->slug] ?? $this->name;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
