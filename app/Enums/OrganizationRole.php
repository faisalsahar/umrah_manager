<?php

namespace App\Enums;

/**
 * A user's role within one organization (see Organization::members()). Roles are
 * few, fixed, and checked through policies/gates rather than a permissions package —
 * see docs/PLAN.md §6 for why.
 */
enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case DataEntry = 'data_entry';
    case Supervisor = 'supervisor';
    case Accountant = 'accountant';
    case AgentUser = 'agent_user';

    public function label(): string
    {
        return match ($this) {
            self::Owner => __('Owner'),
            self::Admin => __('Admin'),
            self::DataEntry => __('Data Entry'),
            self::Supervisor => __('Supervisor'),
            self::Accountant => __('Accountant'),
            self::AgentUser => __('Agent'),
        };
    }

    /**
     * Owner/Admin manage the organization itself: members, settings, contracts.
     */
    public function managesOrganization(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /**
     * Which roles make sense to assign within an organization of the given type —
     * e.g. AgentUser only means something inside an agent organization. Drives both
     * the "add a member" role picker and server-side validation of it.
     *
     * @return list<self>
     */
    public static function availableFor(OrganizationType $type): array
    {
        return match ($type) {
            OrganizationType::Operator => [self::Owner, self::Admin, self::DataEntry, self::Supervisor, self::Accountant],
            OrganizationType::Agent => [self::Owner, self::Admin, self::AgentUser],
            OrganizationType::Hotel, OrganizationType::TransportSupplier, OrganizationType::Platform => [self::Owner, self::Admin],
        };
    }
}
