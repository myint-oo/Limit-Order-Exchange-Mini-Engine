<?php

use Illuminate\Support\Facades\Broadcast;

$pusherKey = config('broadcasting.connections.pusher.key');

if ($pusherKey && config('broadcasting.default') === 'pusher') {
    Broadcast::channel('user.{id}', function ($user, $id) {
        return (int) $user->id === (int) $id;
    });
}
