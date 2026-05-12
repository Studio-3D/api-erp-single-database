<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;  // CHANGE THIS LINE
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AvancesEvent implements ShouldBroadcastNow  // CHANGE THIS INTERFACE
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $reservationId;
    public $userId;

    public function __construct($reservationId, $userId = null)
    {
        $this->reservationId = $reservationId;
        $this->userId = $userId;

        // Log the event creation (optional but helpful for debugging)
        \Log::info('AvancesEvent constructed', [
            'reservationId' => $reservationId,
            'userId' => $userId,
            'connection' => 'pusher_7'
        ]);
    }

    public function broadcastOn()
    {
        \Log::info('AvancesEvent broadcastOn called', [
            'reservationId' => $this->reservationId,
            'userId' => $this->userId
        ]);

        // Broadcast to reservation-specific channel
        if ($this->userId) {
            // User-specific channel
            return new Channel("res-show-user-{$this->userId}");
        } else {
            // Fallback to reservation-specific channel
            return new Channel("avances-updates-{$this->reservationId}");
        }
    }

    // Specify the connection to use
    public function broadcastConnection()
    {
        return 'pusher_7';
    }

    public function broadcastAs()
    {
        return 'AvancesEvent';
    }


    public function broadcastWith()
    {
        return [
            'reservationId' => $this->reservationId,
            'userId' => $this->userId,
            'timestamp' => now()->toISOString()
        ];
    }
}
