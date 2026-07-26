<?php

namespace App\Models;

use App\Enums\LedgerEntryType;
use Database\Factories\LedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row in an organization's cash book, describing money moving against a
 * counterparty. See Organization::balanceWith() — balances are always summed from
 * these rows, never stored, so nothing can fall out of sync.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $counterparty_organization_id
 * @property int|null $operating_order_id
 * @property LedgerEntryType $entry_type
 * @property int $amount_cents
 * @property string $currency
 * @property string|null $description
 * @property Carbon $occurred_on
 */
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_id', 'counterparty_organization_id', 'operating_order_id',
        'entry_type', 'amount_cents', 'currency', 'description', 'occurred_on',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => LedgerEntryType::class,
            'occurred_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'counterparty_organization_id');
    }

    /**
     * @return BelongsTo<OperatingOrder, $this>
     */
    public function operatingOrder(): BelongsTo
    {
        return $this->belongsTo(OperatingOrder::class);
    }
}
