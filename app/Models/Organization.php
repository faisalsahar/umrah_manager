<?php

namespace App\Models;

use App\Concerns\HasBilingualName;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A party in the trade: an Umrah operator, an agent, a hotel, a transport supplier,
 * or the platform itself. People act through membership in an organization (see
 * members()) — an organization never has a login of its own. See docs/PLAN.md §1-2.
 *
 * `parent_organization_id` covers two cases the reference system tags separately:
 * a sub-agent under a larger agency, and a tourism company acting as an external
 * agent of another Umrah operator. Both are "this organization sits under that
 * one" — one nullable self-reference, not a rigid type enum.
 *
 * @property int $id
 * @property OrganizationType $type
 * @property int|null $parent_organization_id
 * @property string $name_ar
 * @property string $name_en
 * @property string $name (accessor, see HasBilingualName)
 * @property string|null $country
 * @property string|null $license_no
 * @property string|null $external_code
 * @property bool $is_active
 * @property Carbon|null $subscription_expires_at
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasBilingualName, HasFactory;

    protected $fillable = [
        'type', 'parent_organization_id', 'name', 'name_ar', 'name_en',
        'country', 'license_no', 'external_code', 'is_active', 'subscription_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'is_active' => 'boolean',
            'subscription_expires_at' => 'date',
        ];
    }

    /**
     * Whether the renewal date the admin set has passed. Informational only — see
     * the migration's docblock — so callers should show a warning, not block access.
     */
    public function isSubscriptionExpired(): bool
    {
        return $this->subscription_expires_at !== null && $this->subscription_expires_at->isPast();
    }

    /**
     * The users who belong to this organization, with their role on the pivot.
     *
     * @return BelongsToMany<User, $this, OrganizationMembership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->using(OrganizationMembership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * This organization's operating orders when it is the operator.
     *
     * @return HasMany<OperatingOrder, $this>
     */
    public function operatingOrders(): HasMany
    {
        return $this->hasMany(OperatingOrder::class, 'operator_id');
    }

    /**
     * The operating orders this organization sent in as the agent.
     *
     * @return HasMany<OperatingOrder, $this>
     */
    public function agentOperatingOrders(): HasMany
    {
        return $this->hasMany(OperatingOrder::class, 'agent_id');
    }

    /**
     * The pilgrims this organization sent in as the agent.
     *
     * @return HasMany<Pilgrim, $this>
     */
    public function pilgrims(): HasMany
    {
        return $this->hasMany(Pilgrim::class, 'agent_id');
    }

    /**
     * Hotels this organization operates (when it is the operator).
     *
     * @return HasMany<Hotel, $this>
     */
    public function hotels(): HasMany
    {
        return $this->hasMany(Hotel::class, 'operator_id');
    }

    /**
     * Vehicle types this organization defines (when it is the operator).
     *
     * @return HasMany<VehicleType, $this>
     */
    public function vehicleTypes(): HasMany
    {
        return $this->hasMany(VehicleType::class, 'operator_id');
    }

    /**
     * The ledger rows in this organization's own book.
     *
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'organization_id');
    }

    /**
     * What the given counterparty owes this organization, in minor currency units.
     * Positive means the counterparty owes us; negative means we owe them.
     *
     * Always computed from ledger_entries — nothing is ever stored, so there is no
     * running balance to fall out of sync. See docs/PLAN.md §7.
     */
    public function balanceWith(Organization $counterparty): int
    {
        return $this->ledgerEntries()
            ->where('counterparty_organization_id', $counterparty->id)
            ->get()
            ->sum(fn (LedgerEntry $entry): int => $entry->entry_type->increasesReceivable()
                ? $entry->amount_cents
                : -$entry->amount_cents
            );
    }

    /**
     * Scope to organizations of a given type.
     *
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function scopeOfType(Builder $query, OrganizationType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Add a user to this organization with the given role. Idempotent: promotes/demotes
     * an existing membership rather than duplicating the pivot row.
     */
    public function addMember(User $user, OrganizationRole $role): void
    {
        $this->members()->syncWithoutDetaching([$user->id => ['role' => $role->value]]);
    }

    /**
     * The organization this one sits under, if any (a sub-agent's parent agency, or
     * an agent acting under another operator).
     *
     * @return BelongsTo<Organization, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'parent_organization_id');
    }

    /**
     * Organizations that sit under this one.
     *
     * @return HasMany<Organization, $this>
     */
    public function subAgents(): HasMany
    {
        return $this->hasMany(Organization::class, 'parent_organization_id');
    }

    /**
     * Whether this organization sits (directly) under the given parent.
     */
    public function isSubAgentOf(Organization $parent): bool
    {
        return $this->parent_organization_id === $parent->id;
    }
}
