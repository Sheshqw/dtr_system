<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyDtr extends Model
{
    protected $table = 'daily_dtr';

    protected $fillable = [
        'employee_id', 'work_date',
        'morning_in', 'morning_out', 'afternoon_in', 'afternoon_out',
        'hours_rendered', 'tardiness_minutes', 'overtime_minutes', 'undertime_minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'work_date'      => 'date',
            'hours_rendered' => 'float',
        ];
    }

    public function employee()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
