<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtrLock extends Model
{
    protected $fillable = [
        'campus_id', 'year', 'month', 'locked_by', 'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'locked_at' => 'datetime',
        ];
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    /**
     * Whether the given campus's month is currently locked. Accepts any date
     * that falls within the target month, so callers with a work_date/punch
     * timestamp don't need to pull the year/month apart themselves.
     */
    public static function isLocked(?int $campusId, \Carbon\Carbon|string $withinMonth): bool
    {
        if (! $campusId) {
            return false;
        }

        $date = $withinMonth instanceof \Carbon\Carbon ? $withinMonth : \Carbon\Carbon::parse($withinMonth);

        return static::where('campus_id', $campusId)
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->exists();
    }
}
