<?php

namespace App\Models;

use App\Concerns\HasBilingualName;
use App\Enums\LocationType;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A named place within or near a city — a port, a train station, a ziyarat site, a
 * stopover, a tourist destination. One table for all of these (see LocationType)
 * rather than a table per kind, since none of them differ structurally.
 *
 * @property int $id
 * @property int $operator_id
 * @property int|null $city_id
 * @property LocationType $type
 * @property string $name_ar
 * @property string $name_en
 * @property string $name (accessor, see HasBilingualName)
 */
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasBilingualName, HasFactory;

    protected $fillable = ['operator_id', 'city_id', 'type', 'name_ar', 'name_en'];

    protected function casts(): array
    {
        return ['type' => LocationType::class];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'operator_id');
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @param  Builder<Location>  $query
     * @return Builder<Location>
     */
    public function scopeOfType(Builder $query, LocationType $type): Builder
    {
        return $query->where('type', $type);
    }
}
