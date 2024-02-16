<?php
// app/Events/PropositionUpdated.php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class PropositionUpdated implements ShouldBroadcast
{
    use SerializesModels;

    public $bienId;
    public $userId;

    /**
     * Create a new event instance.
     *
     * @param int $bienId
     * @param int $userId
     */
    public function __construct($bienId, $userId)
    {
        $this->bienId = $bienId;
        $this->userId = $userId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return new Channel('proposition-updates');
    }
}
