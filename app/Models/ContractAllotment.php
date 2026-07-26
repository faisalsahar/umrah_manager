<?php

namespace App\Models;

use Database\Factories\ContractAllotmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Rooms held per night, per room type, under a contract. `rooms_allotted` is a
 * ceiling of physical rooms — deliberately NOT a bed count, because a shared room is
 * gender-locked once occupied and free beds are not simply fungible (see
 * docs/PLAN.md §4). Computing real, gender-aware nightly availability from this
 * table plus hotel_stays is left as documented follow-up work.
 *
 * @property int $id
 * @property int $hotel_contract_id
 * @property int $room_type_id
 * @property Carbon $date
 * @property int $rooms_allotted
 */
class ContractAllotment extends Model
{
    /** @use HasFactory<ContractAllotmentFactory> */
    use HasFactory;

    protected $fillable = ['hotel_contract_id', 'room_type_id', 'date', 'rooms_allotted'];

    protected function casts(): array
    {
        return ['date' => 'date'];
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
}
