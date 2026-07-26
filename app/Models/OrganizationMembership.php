<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * The `organization_user` pivot, typed so `$membership->role` resolves statically
 * instead of the framework's generic, untyped Pivot class.
 *
 * @property int $organization_id
 * @property int $user_id
 * @property string $role
 */
class OrganizationMembership extends Pivot
{
    protected $table = 'organization_user';

    protected $fillable = ['organization_id', 'user_id', 'role'];
}
