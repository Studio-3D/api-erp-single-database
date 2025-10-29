<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AvancesEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

   // public $avanceData;
    public $reservationId;

    public function __construct($reservationId)
    {
        $this->reservationId = $reservationId;
               config(['broadcasting.default' => 'pusher_7']);

    }

    public function broadcastOn()
    {
        // Broadcast to reservation-specific channel
        return new Channel('avances-updates-' . $this->reservationId);
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
        // Fix: Access specific array elements, not the entire array
        return [
            'reservationId' => $this->reservationId,
            'timestamp' => now()->toISOString()
        ];
    }
}
