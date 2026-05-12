<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;  // CHANGE THIS
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewProjectEvent implements ShouldBroadcastNow  // CHANGE THIS
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $projetData;

    public function __construct($projetData)
    {
        $this->projetData = $projetData;

        // Optional: Add logging for debugging
        \Log::info('NewProjectEvent constructed', [
            'projetData' => $projetData
        ]);
    }

    public function broadcastOn()
    {
        \Log::info('NewProjectEvent broadcastOn called', [
            'channel' => 'projets'
        ]);

        return new Channel('projets');
    }

    // Optional but recommended: Add broadcastAs method
    public function broadcastAs()
    {
        return 'NewProjectEvent';
    }

    // Optional: Add data to broadcast
    public function broadcastWith()
    {
        return [
            'projetData' => $this->projetData,
            'timestamp' => now()->toISOString()
        ];
    }


     public function broadcastConnection()
    {
        return 'pusher_2';
    }
}
