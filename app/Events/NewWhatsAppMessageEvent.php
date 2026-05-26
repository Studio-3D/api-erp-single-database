<?php
// app/Events/NewWhatsAppMessageEvent.php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewWhatsAppMessageEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;
    public $projetId;
    public $phoneNumber;
    public $conversation;

    public function __construct($message, $projetId, $phoneNumber, $conversation = null)
    {
        $this->message = $message;
        $this->projetId = $projetId;
        $this->phoneNumber = $phoneNumber;
        $this->conversation = $conversation;
    }

 // Specify the connection to use
    public function broadcastConnection()
    {
        return 'pusher_whatsapp';
    }
    public function broadcastOn()
    {
        \Log::info('event whtsp', [
            'channel' => 'WHTSPPP',
            'WHTSP PRROJET' => $this->projetId,
            'WHTSP conversation' =>  $this->projetId . '.' . $this->phoneNumber
        ]);
        return [
            new Channel('whatsapp-project.' . $this->projetId),
            new Channel('whatsapp-conversation.' . $this->projetId . '.' . $this->phoneNumber)
        ];
    }

    public function broadcastAs()
    {
        return 'new-whatsapp-message';
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'projet_id' => $this->projetId,
            'phone_number' => $this->phoneNumber,
            'conversation' => $this->conversation,
            'timestamp' => now()->toISOString()
        ];
    }
}
