<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Public channel used by all POS dashboards (waiter, kitchen, cashier).
Broadcast::channel('pos.orders', function () {
    return true;
});
