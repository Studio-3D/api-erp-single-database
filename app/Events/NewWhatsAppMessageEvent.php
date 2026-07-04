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

    public function broadcastConnection()
    {
        return 'pusher_whatsapp';
    }

    public function broadcastOn()
    {
        // ✅ Clean the phone number: remove + and any other special characters
        $cleanPhoneNumber = preg_replace('/[^a-zA-Z0-9]/', '', $this->phoneNumber);

        \Log::info('event whtsp', [
            'channel' => 'WHTSPPP',
            'WHTSP PROJET' => $this->projetId,
            'WHTSP conversation' => $this->projetId . '.' . $cleanPhoneNumber,
            'original_phone' => $this->phoneNumber,
            'cleaned_phone' => $cleanPhoneNumber
        ]);

        return [
            new Channel('whatsapp-project.' . $this->projetId),
            new Channel('whatsapp-conversation.' . $this->projetId . '.' . $cleanPhoneNumber)
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
