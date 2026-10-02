<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast the instant an employee submits a new DTR print request, so an
 * HR/Admin already sitting on the Reports page sees it appear without
 * having to reload — same broadcasting pattern as AttendancePunched.
 */
class PrintConsentRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly array $consent,
        public readonly ?string $campus_code,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('print-consents');
    }

    public function broadcastAs(): string
    {
        return 'requested';
    }
}
