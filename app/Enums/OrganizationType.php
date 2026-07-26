<?php

namespace App\Enums;

/**
 * Every party that transacts on the platform is an organization of one of these
 * types. A person's powers come from their membership in an organization (see
 * OrganizationRole), never from the user row itself.
 *
 * NOTE: this is deliberately a single type per organization for now. Whether one
 * organization can hold two capabilities at once (an operator that is also its own
 * transport supplier, for example) is an open product question — see docs/PLAN.md
 * §11. If the answer becomes yes, this becomes a capability set rather than an enum.
 */
enum OrganizationType: string
{
    case Operator = 'operator';
    case Agent = 'agent';
    case Hotel = 'hotel';
    case TransportSupplier = 'transport_supplier';
    case Platform = 'platform';

    public function label(): string
    {
        return match ($this) {
            self::Operator => __('Umrah Operator'),
            self::Agent => __('Agent'),
            self::Hotel => __('Hotel'),
            self::TransportSupplier => __('Transport Supplier'),
            self::Platform => __('Platform'),
        };
    }
}
