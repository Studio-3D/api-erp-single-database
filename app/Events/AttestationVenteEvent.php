<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;  // CHANGE THIS LINE
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttestationVenteEvent implements ShouldBroadcastNow  // CHANGE THIS INTERFACE
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $reservationId;

    public function __construct($reservationId)
    {
        $this->reservationId = $reservationId;

        // Log the event creation (optional but helpful for debugging)
        \Log::info('AttestationVenteEvent constructed', [
            'reservationId' => $reservationId,
            'connection' => 'pusher_9'
        ]);
    }

    public function broadcastOn()
    {
        \Log::info('AttestationVenteEvent broadcastOn called', [
            'channel' => 'attestation-vente-updates-' . $this->reservationId
        ]);

        // Broadcast to reservation-specific channel
        return new Channel('attestation-vente-updates-' . $this->reservationId);
    }

    // Specify the connection to use
    public function broadcastConnection()
    {
        return 'pusher_9';
    }

    public function broadcastAs()
    {
        return 'AttestationVenteEvent';
    }


    public function broadcastWith()
    {
        return [
            'reservationId' => $this->reservationId,
            'timestamp' => now()->toISOString()
        ];
    }
}
