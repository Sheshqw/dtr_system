<?php

namespace App\Models;

use App\Support\DtrPeriod;
use Illuminate\Database\Eloquent\Model;

class DtrPrintConsent extends Model
{
    protected $table = 'dtr_print_consents';

    protected $fillable = [
        'employee_id', 'month', 'year', 'period', 'purpose',
        'status', 'reviewed_by', 'reviewed_at', 'reviewer_remarks', 'used_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'used_at'     => 'datetime',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function dtrPeriod(): DtrPeriod
    {
        return new DtrPeriod($this->year, $this->month, $this->period ?? DtrPeriod::FULL);
    }

    /** "October 2026", or "October 1–15, 2026" for a half-month request. */
    public function getMonthLabelAttribute(): string
    {
        return $this->dtrPeriod()->label();
    }
}
