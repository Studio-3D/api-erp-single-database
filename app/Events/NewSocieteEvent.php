<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;  // CHANGE THIS
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewSocieteEvent implements ShouldBroadcastNow  // CHANGE THIS
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $societeData;


    public function __construct($societeData)
    {
        $this->societeData = $societeData;

        // Optional: Add logging for debugging
        \Log::info('NewSocieteEvent constructed', [
            'societeData' => $societeData
        ]);
    }

    public function broadcastOn()
    {
        \Log::info('NewSocieteEvent broadcastOn called', [
            'channel' => 'societes'
        ]);

        return new Channel('societes');
    }

    // Optional but recommended: Add broadcastAs method
    public function broadcastAs()
    {
        return 'NewSocieteEvent';
    }

    // Optional: Add data to broadcast
    public function broadcastWith()
    {
        return [
            'societeData' => $this->societeData,
            'timestamp' => now()->toISOString()
        ];
    }
     public function broadcastConnection()
    {
        return 'pusher_1';
    }
}

