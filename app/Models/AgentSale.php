<?php

namespace App\Models;

use App\Enums\AgentSalePaymentDirection;
use App\Enums\AgentSaleStatus;
use App\Enums\AgentSaleType;
use Database\Factories\AgentSaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One sale an agent makes directly to its own customer — a flight ticket or
 * anything else — entirely independent of any operator or operating order. See
 * OrderLine for the analogous "purchase vs sale price gives a margin" pattern this
 * reuses; see AgentSalePayment for why paid amounts are summed rather than stored.
 *
 * @property int $id
 * @property string $reference_no
 * @property int $agent_organization_id
 * @property int|null $recorded_by_user_id
 * @property AgentSaleType $sale_type
 * @property AgentSaleStatus $status
 * @property string $customer_name
 * @property string|null $description
 * @property string|null $supplier_reference
 * @property Carbon $sale_date
 * @property int $purchase_price_cents
 * @property int $sale_price_cents
 * @property string $currency
 */
class AgentSale extends Model
{
    /** @use HasFactory<AgentSaleFactory> */
    use HasFactory;

    protected $fillable = [
        'reference_no', 'agent_organization_id', 'recorded_by_user_id', 'sale_type', 'status',
        'customer_name', 'description', 'supplier_reference', 'sale_date',
        'purchase_price_cents', 'sale_price_cents', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'sale_type' => AgentSaleType::class,
            'status' => AgentSaleStatus::class,
            'sale_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AgentSale $sale): void {
            if (blank($sale->reference_no)) {
                $sale->reference_no = static::generateReferenceNo();
            }
        });
    }

    /**
     * A short, unique, human-friendly reference (e.g. "SL-K7P2QX").
     */
    public static function generateReferenceNo(): string
    {
        do {
            $code = 'SL-'.Str::upper(Str::random(6));
        } while (static::where('reference_no', $code)->exists());

        return $code;
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function agentOrganization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'agent_organization_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * @return HasMany<AgentSalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(AgentSalePayment::class);
    }

    /**
     * This sale's margin: sale minus purchase — the agent's commission/profit.
     */
    public function marginCents(): int
    {
        return $this->sale_price_cents - $this->purchase_price_cents;
    }

    /**
     * How much of the customer's side has been collected so far.
     */
    public function amountReceivedCents(): int
    {
        return $this->paymentsSum(AgentSalePaymentDirection::FromCustomer);
    }

    /**
     * How much has been remitted to the supplier/airline so far.
     */
    public function amountPaidToSupplierCents(): int
    {
        return $this->paymentsSum(AgentSalePaymentDirection::ToSupplier);
    }

    /**
     * What the customer still owes on this sale.
     */
    public function customerOutstandingCents(): int
    {
        return $this->sale_price_cents - $this->amountReceivedCents();
    }

    /**
     * What's still owed to the supplier/airline on this sale.
     */
    public function supplierOutstandingCents(): int
    {
        return $this->purchase_price_cents - $this->amountPaidToSupplierCents();
    }

    private function paymentsSum(AgentSalePaymentDirection $direction): int
    {
        return $this->payments
            ->where('direction', $direction)
            ->sum('amount_cents');
    }
}
