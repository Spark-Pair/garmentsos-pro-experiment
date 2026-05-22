<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NewNotificationEvent implements ShouldBroadcast
{
    public $data;

    public function __construct($data)
    {
        $payload = is_array($data) ? $data : [];

        $this->data = array_merge([
            'type' => 'info',
            'title' => '',
            'message' => '',
            'id' => null,
            'timestamp' => now()->toIso8601String(),
        ], $payload);
    }

    public function broadcastOn()
    {
        // Check Pusher flag
        if (!app('pusher.enabled')) {
            return []; // Return empty array → event will not broadcast
        }

        return new Channel('notifications'); // Public channel
    }
}
