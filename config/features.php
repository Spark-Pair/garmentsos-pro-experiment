<?php

return [
    'pusher_notifications' => [
        'label' => 'Pusher Notifications',
        'default_enabled' => (bool) env('PUSHER_ENABLED', false),
        'description' => 'Realtime browser notifications foundation.',
    ],
    'developer_backups' => [
        'label' => 'Developer Backups',
        'default_enabled' => true,
        'description' => 'Developer/admin backup tooling.',
    ],
];
