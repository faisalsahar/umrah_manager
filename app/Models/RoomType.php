<?php

namespace App\Models;

use Database\Factories\RoomTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * `capacity` is the bed count (2 = double, 3 = triple, 4 = quad, …). The sellable
 * unit in shared-room selling is the BED, not the room — see docs/PLAN.md §4.
 *
 * @property int $id
 * @property int $hotel_id
 * @property string $name
 * @property int $capacity
 */
class RoomType extends Model
{
    /** @use HasFactory<RoomTypeFactory> */
    use HasFactory;

    protected $fillable = ['hotel_id', 'name', 'capacity'];

    /**
     * @return BelongsTo<Hotel, $this>
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * @return HasMany<ContractRate, $this>
     */
    public function contractRates(): HasMany
    {
        return $this->hasMany(ContractRate::class);
    }

    /**
     * @return HasMany<ContractAllotment, $this>
     */
    public function contractAllotments(): HasMany
    {
        return $this->hasMany(ContractAllotment::class);
    }

    /**
     * @return HasMany<HotelStay, $this>
     */
    public function stays(): HasMany
    {
        return $this->hasMany(HotelStay::class);
    }
}
