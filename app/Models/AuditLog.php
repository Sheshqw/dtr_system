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

    /**
     * $userId overrides the default "currently authenticated actor" — needed
     * for auth events (login/lockout), where the subject of the log entry
     * isn't authenticated yet when the entry is written.
     */
    public static function record(
        string $action,
        string $table,
        ?int $recordId,
        mixed $old,
        mixed $new,
        ?string $reason = null,
        ?int $userId = null,
    ): void {
        static::create([
            'user_id'        => $userId ?? auth()->id(),
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
