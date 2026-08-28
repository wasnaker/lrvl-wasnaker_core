<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Auth callback untuk private channel. Channel `user.{id}` hanya bisa
| didengarkan oleh user dengan id yang sama (pengganti App_pusher Perfex).
|
*/

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
