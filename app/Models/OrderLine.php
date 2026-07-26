<?php

namespace App\Models;

use App\Enums\OrderLineType;
use Database\Factories\OrderLineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What was actually SOLD on an operating order (as opposed to what is being
 * serviced — see docs/PLAN.md §3). Exactly one of hotelStay()/movement()/service()
 * is populated, matching `line_type`. Purchase vs sale price on the same row is
 * what gives a margin per line; money is minor units + currency, never a float.
 *
 * @property int $id
 * @property int $operating_order_id
 * @property OrderLineType $line_type
 * @property int|null $hotel_stay_id
 * @property int|null $movement_id
 * @property int|null $service_id
 * @property string|null $description
 * @property int $quantity
 * @property int $purchase_price_cents
 * @property int $sale_price_cents
 * @property string $currency
 */
class OrderLine extends Model
{
    /** @use HasFactory<OrderLineFactory> */
    use HasFactory;

    protected $fillable = [
        'operating_order_id', 'line_type', 'hotel_stay_id', 'movement_id', 'service_id',
        'description', 'quantity', 'purchase_price_cents', 'sale_price_cents', 'currency',
    ];

    protected function casts(): array
    {
        return ['line_type' => OrderLineType::class];
    }

    /**
     * @return BelongsTo<OperatingOrder, $this>
     */
    public function operatingOrder(): BelongsTo
    {
        return $this->belongsTo(OperatingOrder::class);
    }

    /**
     * @return BelongsTo<HotelStay, $this>
     */
    public function hotelStay(): BelongsTo
    {
        return $this->belongsTo(HotelStay::class);
    }

    /**
     * @return BelongsTo<Movement, $this>
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * This line's margin: sale minus purchase, times quantity.
     */
    public function marginCents(): int
    {
        return ($this->sale_price_cents - $this->purchase_price_cents) * $this->quantity;
    }
}
