<?php

namespace App\Events;
use App\Models\Alert;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $alert;
    public $eventId;
    /**
     * Create a new event instance.
     */
    public function __construct(Alert $alert, $eventId)
    {
        $this->alert = $alert;
        $this->eventId = $eventId;
    }
    public function broadcastOn(): array
    {
        return [
            new Channel('events.' . $this->eventId),
        ];
    }

    public function broadcastAs() : string {
        return 'alert:new';
    }
}
