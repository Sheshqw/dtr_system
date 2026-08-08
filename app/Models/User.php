<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'employees';

    protected $fillable = [
        'employee_id', 'first_name', 'last_name', 'middle_name', 'suffix',
        'gender', 'civil_status',
        'campus_id', 'department_id', 'shift_id', 'position', 'employment_status',
        'staff_type', 'contact_number', 'date_of_birth', 'date_hired',
        'gsis_number', 'philhealth_number', 'pagibig_number', 'tin_number',
        'email', 'password', 'role', 'fingerprint_id', 'photo_path',
        'is_active', 'failed_login_attempts', 'locked_until',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password'       => 'hashed',
            'is_active'      => 'boolean',
            'locked_until'   => 'datetime',
            'date_of_birth'  => 'date:Y-m-d',
            'date_hired'     => 'date:Y-m-d',
        ];
    }

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([$this->first_name, $this->middle_name, $this->last_name, $this->suffix]);
        return trim(implode(' ', $parts));
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function campus()
    {
        return $this->belongsTo(Campus::class);
    }

    public function isHrOfficer(): bool
    {
        return $this->role === 'hr_officer';
    }

    public function isDepartmentHead(): bool
    {
        return $this->role === 'department_head';
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class, 'employee_id');
    }

    public function dailyDtr()
    {
        return $this->hasMany(DailyDtr::class, 'employee_id');
    }

}
