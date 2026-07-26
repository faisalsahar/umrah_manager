<?php

namespace App\Models;

use App\Concerns\HasBilingualName;
use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An operator-curated city — bilingual, tagged with what it's used for (a city can
 * be a port, an accommodation base, a rail stop, or several at once). See
 * docs/PLAN.md §3 on why this replaced free-text from/to locations.
 *
 * @property int $id
 * @property int $operator_id
 * @property string $name_ar
 * @property string $name_en
 * @property string $name (accessor, see HasBilingualName)
 * @property bool $is_port
 * @property bool $is_accommodation
 * @property bool $has_train
 */
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasBilingualName, HasFactory;

    protected $fillable = ['operator_id', 'name_ar', 'name_en', 'is_port', 'is_accommodation', 'has_train'];

    protected function casts(): array
    {
        return [
            'is_port' => 'boolean',
            'is_accommodation' => 'boolean',
            'has_train' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'operator_id');
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }
}
