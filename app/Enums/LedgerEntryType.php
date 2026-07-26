<?php

namespace App\Enums;

/**
 * A ledger entry always belongs to one organization's book (`organization_id`) and
 * describes money moving against a counterparty. Charge/Debit increase what the
 * counterparty owes us; Payment/Credit decrease it. See Organization::balanceWith().
 */
enum LedgerEntryType: string
{
    case Charge = 'charge';       // we billed the counterparty (e.g. operator -> agent)
    case Payment = 'payment';     // counterparty paid us
    case Credit = 'credit';       // manual credit note/adjustment in the counterparty's favour
    case Debit = 'debit';         // manual debit/adjustment against the counterparty

    public function label(): string
    {
        return match ($this) {
            self::Charge => __('Charge'),
            self::Payment => __('Payment'),
            self::Credit => __('Credit'),
            self::Debit => __('Debit'),
        };
    }

    /**
     * Whether this entry type increases (true) or decreases (false) the amount the
     * counterparty owes us.
     */
    public function increasesReceivable(): bool
    {
        return match ($this) {
            self::Charge, self::Debit => true,
            self::Payment, self::Credit => false,
        };
    }
}
