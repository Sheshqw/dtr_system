<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast the instant a print request is approved/denied, so any other
 * HR/Admin tab viewing the same queue drops it from "pending" live (instead
 * of risking a stale double-review), and the employee's own view updates
 * without a reload.
 */
class PrintConsentDecided implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly array $consent,
        public readonly int $employee_id,
        public readonly ?string $campus_code,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('print-consents');
    }

    public function broadcastAs(): string
    {
        return 'decided';
    }
}
