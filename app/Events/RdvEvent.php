<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;  // CHANGE THIS
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RdvEvent implements ShouldBroadcastNow  // CHANGE THIS
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $reservationId;

    public function __construct($reservationId)
    {
        $this->reservationId = $reservationId;

        // Remove the config line below - not needed with broadcastConnection()
        // config(['broadcasting.default' => 'pusher_8']);

        // Optional: Add logging for debugging
        \Log::info('RdvEvent constructed', [
            'reservationId' => $reservationId,
            'connection' => 'pusher_8'
        ]);
    }

    public function broadcastOn()
    {
        \Log::info('RdvEvent broadcastOn called', [
            'reservationId' => $this->reservationId,
            'channel' => 'rdv-list-updates-' . $this->reservationId
        ]);

        // Broadcast to reservation-specific channel
        return new Channel('rdv-list-updates-' . $this->reservationId);
    }

    // Specify the connection to use
    public function broadcastConnection()
    {
        return 'pusher_8';
    }

    public function broadcastAs()
    {
        return 'RdvEvent';
    }


    public function broadcastWith()
    {
        return [
            'reservationId' => $this->reservationId,
            'timestamp' => now()->toISOString()
        ];
    }
}
