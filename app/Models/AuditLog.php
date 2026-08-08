<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'table_affected',
        'record_id', 'old_value', 'new_value',
        'reason', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function record(string $action, string $table, int $recordId, mixed $old, mixed $new, ?string $reason = null): void
    {
        static::create([
            'user_id'        => auth()->id(),
            'action'         => $action,
            'table_affected' => $table,
            'record_id'      => $recordId,
            'old_value'      => $old,
            'new_value'      => $new,
            'reason'         => $reason,
            'ip_address'     => request()->ip(),
        ]);
    }
}
