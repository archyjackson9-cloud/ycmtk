<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

/**
 * TOR §6.10 RBAC - "Customers - own orders and profile only" vs staff, who
 * may view/update any order within their role's limits.
 */
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($order->user_id === $user->id) {
            return true;
        }

        return $user->hasAnyRole([
            config('cymarket.roles.super_admin'),
            config('cymarket.roles.inventory_officer'),
            config('cymarket.roles.support_agent'),
        ]);
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->hasAnyRole([
            config('cymarket.roles.super_admin'),
            config('cymarket.roles.inventory_officer'),
        ]);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->view($user, $order);
    }
}
