<?php

namespace App\Models;

use App\Enums\AgentSalePaymentDirection;
use Database\Factories\AgentSalePaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One payment record against an AgentSale, in either direction — see
 * AgentSalePaymentDirection. Balances are always summed from these rows, never
 * stored, the same discipline LedgerEntry uses for organization-to-organization
 * balances.
 *
 * @property int $id
 * @property int $agent_sale_id
 * @property AgentSalePaymentDirection $direction
 * @property int $amount_cents
 * @property Carbon $paid_on
 * @property string|null $note
 */
class AgentSalePayment extends Model
{
    /** @use HasFactory<AgentSalePaymentFactory> */
    use HasFactory;

    protected $fillable = ['agent_sale_id', 'direction', 'amount_cents', 'paid_on', 'note'];

    protected function casts(): array
    {
        return [
            'direction' => AgentSalePaymentDirection::class,
            'paid_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<AgentSale, $this>
     */
    public function agentSale(): BelongsTo
    {
        return $this->belongsTo(AgentSale::class);
    }
}
