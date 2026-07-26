<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\HotelStaySource;
use App\Enums\HotelStayStatus;
use Database\Factories\HotelStayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A pilgrim's stay, independent of who sold it — `operating_order_id` is nullable so
 * a stay can exist purely because the operator needs to know where a pilgrim
 * sleeps, even when the agent brought their own hotel booking. See docs/PLAN.md §3.
 *
 * @property int $id
 * @property int|null $operating_order_id
 * @property int $hotel_id
 * @property int $room_type_id
 * @property int $pilgrim_id
 * @property Gender $gender
 * @property string|null $room_no
 * @property Carbon $check_in
 * @property Carbon $check_out
 * @property HotelStayStatus $status
 * @property HotelStaySource $source
 * @property string|null $supplier_reference_no
 */
class HotelStay extends Model
{
    /** @use HasFactory<HotelStayFactory> */
    use HasFactory;

    protected $fillable = [
        'operating_order_id', 'hotel_id', 'room_type_id', 'pilgrim_id', 'gender',
        'room_no', 'check_in', 'check_out', 'status', 'source', 'supplier_reference_no',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'check_in' => 'date',
            'check_out' => 'date',
            'status' => HotelStayStatus::class,
            'source' => HotelStaySource::class,
        ];
    }

    protected static function booted(): void
    {
        // The gender column is denormalized from the pilgrim purely for query speed
        // (see docs/PLAN.md §4) — never let it drift from the pilgrim's actual record.
        static::saving(function (HotelStay $stay): void {
            if ($stay->isDirty('pilgrim_id') || blank($stay->gender)) {
                $stay->gender = $stay->pilgrim->gender;
            }
        });
    }

    /**
     * @return BelongsTo<OperatingOrder, $this>
     */
    public function operatingOrder(): BelongsTo
    {
        return $this->belongsTo(OperatingOrder::class);
    }

    /**
     * @return BelongsTo<Hotel, $this>
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * @return BelongsTo<RoomType, $this>
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * @return BelongsTo<Pilgrim, $this>
     */
    public function pilgrim(): BelongsTo
    {
        return $this->belongsTo(Pilgrim::class);
    }

    /**
     * @return HasOne<OrderLine, $this>
     */
    public function orderLine(): HasOne
    {
        return $this->hasOne(OrderLine::class);
    }
}
