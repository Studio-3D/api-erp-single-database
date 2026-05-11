<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('rdv-updates', function ($user) {
    return true; // Or your custom authorization logic
});

Broadcast::channel('proposition-updates', function ($user) {
    \Log::info('Channel authorization', ['user_id' => $user->id]);
    return true;
});
