<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'staff_type', 'morning_in', 'morning_out',
        'afternoon_in', 'afternoon_out',
        'grace_period_minutes', 'required_hours',
    ];

    public function employees()
    {
        return $this->hasMany(User::class, 'shift_id');
    }
}
