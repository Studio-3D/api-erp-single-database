<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;  // CHANGE THIS
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContratVenteEvent implements ShouldBroadcastNow  // CHANGE THIS
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $reservationId;

    public function __construct($reservationId)
    {
        $this->reservationId = $reservationId;

        // Remove the config line below - not needed with broadcastConnection()
        // config(['broadcasting.default' => 'pusher_10']);

        // Optional: Add logging for debugging
        \Log::info('ContratVenteEvent constructed', [
            'reservationId' => $reservationId,
            'connection' => 'pusher_10'
        ]);
    }

    public function broadcastOn()
    {
        \Log::info('ContratVenteEvent broadcastOn called', [
            'reservationId' => $this->reservationId,
            'channel' => 'contrat-vente-updates-' . $this->reservationId
        ]);

        // Broadcast to reservation-specific channel
        return new Channel('contrat-vente-updates-' . $this->reservationId);
    }

    // Specify the connection to use
    public function broadcastConnection()
    {
        return 'pusher_10';
    }


    public function broadcastAs()
    {
        return 'ContratVenteEvent';
    }

    public function broadcastWith()
    {
        return [
            'reservationId' => $this->reservationId,
            'timestamp' => now()->toISOString()
        ];
    }
}
