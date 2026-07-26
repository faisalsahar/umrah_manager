<?php

namespace App\Policies;

use App\Models\OperatingOrder;
use App\Models\User;

/**
 * The visibility rule from docs/PLAN.md §3/§7, enforced here rather than scattered
 * across controllers/components: the operator sees every order it operates, an
 * agent sees only the orders it sent in. Everything else (data entry, supervisors,
 * accountants) inherits the operator's visibility since they act as/for it.
 */
class OperatingOrderPolicy
{
    public function view(User $user, OperatingOrder $order): bool
    {
        return $user->belongsToOrganization($order->operator)
            || ($order->agent && $user->belongsToOrganization($order->agent));
    }

    public function create(User $user): bool
    {
        // Anyone acting as an operator or an agent may open an order; the operator_id
        // on the order itself is set from the user's current organization.
        return $user->current_organization_id !== null;
    }

    public function update(User $user, OperatingOrder $order): bool
    {
        // Only the operator side may edit an order once it exists — an agent's
        // "editing" is submitting a new request, not mutating the operator's record.
        return $user->belongsToOrganization($order->operator);
    }

    public function delete(User $user, OperatingOrder $order): bool
    {
        return $user->belongsToOrganization($order->operator);
    }
}
