<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class DtrPrintConsent extends Model
{
    protected $table = 'dtr_print_consents';

    protected $fillable = [
        'employee_id', 'month', 'year', 'purpose',
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

    public function getMonthLabelAttribute(): string
    {
        return Carbon::create($this->year, $this->month)->format('F Y');
    }
}
