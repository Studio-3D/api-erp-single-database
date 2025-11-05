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
    public $userId;

    public function __construct($reservationId,$userId=null)
    {
        $this->reservationId = $reservationId;
        $this->userId = $userId;
               config(['broadcasting.default' => 'pusher_7']);

    }

    public function broadcastOn()
    {
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
        // Fix: Access specific array elements, not the entire array
        return [
            'reservationId' => $this->reservationId,
            'userId' => $this->userId,
            'timestamp' => now()->toISOString()
        ];
    }
}
