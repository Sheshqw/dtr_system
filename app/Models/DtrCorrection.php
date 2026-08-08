<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DtrCorrection extends Model
{
    protected $table = 'dtr_corrections';

    protected $fillable = [
        'employee_id', 'work_date', 'field',
        'original_value', 'requested_value', 'reason',
        'status', 'reviewed_by', 'reviewed_at', 'reviewer_remarks',
    ];

    protected function casts(): array
    {
        return [
            'work_date'   => 'date',
            'reviewed_at' => 'datetime',
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

    public function dailyDtr()
    {
        return $this->hasOne(DailyDtr::class, 'employee_id', 'employee_id')
            ->where('work_date', $this->work_date);
    }

    public function getFieldLabelAttribute(): string
    {
        return match ($this->field) {
            'morning_in'    => 'Morning Time-In',
            'morning_out'   => 'Morning Time-Out',
            'afternoon_in'  => 'Afternoon Time-In',
            'afternoon_out' => 'Afternoon Time-Out',
            default         => $this->field,
        };
    }
}
