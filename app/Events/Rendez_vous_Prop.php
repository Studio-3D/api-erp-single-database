<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;  // CHANGE THIS
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Rendez_vous_Prop implements ShouldBroadcastNow  // CHANGE THIS
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $newDate;
    public $oldDate;
    public $userId;
    public $reservationId;

    public function __construct($newDate, $userId, $reservationId, $oldDate = null)
    {
        $this->newDate = $newDate;
        $this->oldDate = $oldDate;
        $this->userId = $userId;
        $this->reservationId = $reservationId;

        // Optional: Add logging for debugging
        \Log::info('Rendez_vous_Prop event constructed', [
            'newDate' => $newDate,
            'oldDate' => $oldDate,
            'userId' => $userId,
            'reservationId' => $reservationId,
            'connection' => 'pusher_6'
        ]);
    }

    public function broadcastOn()
    {
        \Log::info('Rendez_vous_Prop broadcastOn called', [
            'channel' => 'rdv-updates'
        ]);

        return new Channel('rdv-updates');
    }

    // Specify the connection to use
    public function broadcastConnection()
    {
        return 'pusher_6';
    }

    public function broadcastAs()
    {
        return 'Rendez_vous_Prop';
    }


    public function broadcastWith()
    {
        return [
            'newDate' => $this->newDate,
            'oldDate' => $this->oldDate,
            'userId' => $this->userId,
            'reservationId' => $this->reservationId,
            'timestamp' => now()->toISOString()  // Added timestamp for better tracking
        ];
    }
}
