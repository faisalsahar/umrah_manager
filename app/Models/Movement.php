<?php

namespace App\Models;

use App\Enums\MovementSource;
use App\Enums\MovementStatus;
use Database\Factories\MovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * One vehicle, one route leg, one departure time. A movement can exist even when
 * the operator sold no transport line for it, which is why it does not require an
 * order_line — see docs/PLAN.md §3 and §5.
 *
 * `source` mirrors HotelStay::$source: transport can be arranged by the operator or
 * brought by the agent, same as a hotel stay can be sold or provided.
 *
 * @property int $id
 * @property int $operating_order_id
 * @property int $movement_type_id
 * @property int|null $vehicle_id
 * @property int|null $transport_supplier_id
 * @property MovementSource $source
 * @property string|null $supplier_reference_no
 * @property MovementStatus $status
 * @property string|null $from_location
 * @property string|null $to_location
 * @property int|null $stopover_location_id
 * @property Carbon|null $movement_at
 * @property int $pilgrim_count
 * @property string|null $supervisor_name
 * @property string|null $notes
 */
class Movement extends Model
{
    /** @use HasFactory<MovementFactory> */
    use HasFactory;

    protected $fillable = [
        'operating_order_id', 'movement_type_id', 'vehicle_id', 'transport_supplier_id',
        'source', 'supplier_reference_no', 'status', 'from_location', 'to_location',
        'stopover_location_id', 'movement_at', 'pilgrim_count', 'supervisor_name', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'source' => MovementSource::class,
            'status' => MovementStatus::class,
            'movement_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OperatingOrder, $this>
     */
    public function operatingOrder(): BelongsTo
    {
        return $this->belongsTo(OperatingOrder::class);
    }

    /**
     * @return BelongsTo<MovementType, $this>
     */
    public function movementType(): BelongsTo
    {
        return $this->belongsTo(MovementType::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function transportSupplier(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'transport_supplier_id');
    }

    /**
     * The optional intermediate stop on this route leg.
     *
     * @return BelongsTo<Location, $this>
     */
    public function stopoverLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'stopover_location_id');
    }

    /**
     * The manifest: named pilgrims travelling on this movement.
     *
     * @return BelongsToMany<Pilgrim, $this>
     */
    public function pilgrims(): BelongsToMany
    {
        return $this->belongsToMany(Pilgrim::class, 'movement_pilgrim')->withTimestamps();
    }

    /**
     * @return HasOne<OrderLine, $this>
     */
    public function orderLine(): HasOne
    {
        return $this->hasOne(OrderLine::class);
    }
}
