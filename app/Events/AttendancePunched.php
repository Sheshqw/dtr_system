<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendancePunched implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $name,
        public readonly string $employee_id,
        public readonly string $punch_type,
        public readonly string $time,
        public readonly ?string $campus_code,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('attendance');
    }

    public function broadcastAs(): string
    {
        return 'punched';
    }
}
