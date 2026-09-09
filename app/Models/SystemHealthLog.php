<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemHealthLog extends Model
{
    protected $fillable = [
        'severity', 'type', 'message', 'file', 'line', 'trace', 'context',
        'signature', 'occurrences', 'last_seen_at',
        'status', 'acknowledged_by', 'acknowledged_at', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'context'         => 'array',
            'last_seen_at'    => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at'     => 'datetime',
        ];
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', 'new');
    }
}
