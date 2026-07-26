<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical vehicle. `transport_supplier_id` is null for the operator's own fleet,
 * or points at a contracted transport-supplier organization.
 *
 * @property int $id
 * @property int $operator_id
 * @property int $vehicle_type_id
 * @property int|null $transport_supplier_id
 * @property string|null $plate_no
 * @property string|null $driver_name
 * @property string|null $driver_phone
 * @property bool $is_active
 */
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    protected $fillable = [
        'operator_id', 'vehicle_type_id', 'transport_supplier_id',
        'plate_no', 'driver_name', 'driver_phone', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'operator_id');
    }

    /**
     * @return BelongsTo<VehicleType, $this>
     */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function transportSupplier(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'transport_supplier_id');
    }

    /**
     * @return HasMany<Movement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }
}
