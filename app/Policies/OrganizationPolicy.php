<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

/**
 * Managing an organization itself (its members, its settings) is reserved for
 * Owner/Admin roles within that organization — see OrganizationRole::managesOrganization().
 */
class OrganizationPolicy
{
    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization)?->managesOrganization() ?? false;
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization)?->managesOrganization() ?? false;
    }
}
