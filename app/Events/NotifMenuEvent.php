<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotifMenuEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;



    public $NotifMenuId;

    public function __construct($NotifMenuId)
    {
        $this->NotifMenuId = $NotifMenuId;

    }
    public function broadcastOn()
    {
        return new Channel('NotifMenu');
    }

}
