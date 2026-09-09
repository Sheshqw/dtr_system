<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SystemSetting extends Model
{
    protected $fillable = [
        'system_name', 'short_name', 'tagline', 'logo_path', 'updated_by',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * The single settings row, created with defaults on first access.
     * Defaults are passed explicitly (not left to the migration's DB-level
     * defaults) so the in-memory object is correct immediately — Eloquent's
     * create() doesn't re-sync attributes the database defaulted for you.
     */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'system_name' => 'NEMSU',
            'short_name'  => 'UDTRMS',
            'tagline'     => 'University Daily Time Record Monitoring System',
        ]);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}
