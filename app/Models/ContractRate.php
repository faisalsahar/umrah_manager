<?php

namespace App\Models;

use Database\Factories\ContractRateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Seasonal, date-ranged pricing per bed per night for a room type. Ramadan and
 * peak-season rates are multiples of low-season ones — never a single price column
 * on a hotel. See docs/PLAN.md §4.
 *
 * @property int $id
 * @property int $hotel_contract_id
 * @property int $room_type_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property int $purchase_price_cents
 * @property int $sale_price_cents
 */
class ContractRate extends Model
{
    /** @use HasFactory<ContractRateFactory> */
    use HasFactory;

    protected $fillable = [
        'hotel_contract_id', 'room_type_id', 'starts_on', 'ends_on',
        'purchase_price_cents', 'sale_price_cents',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<HotelContract, $this>
     */
    public function hotelContract(): BelongsTo
    {
        return $this->belongsTo(HotelContract::class);
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Rates whose date range covers the given night.
     *
     * @param  Builder<ContractRate>  $query
     * @return Builder<ContractRate>
     */
    public function scopeCovering(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query->where('starts_on', '<=', $date)->where('ends_on', '>=', $date);
    }
}
