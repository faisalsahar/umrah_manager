<?php

namespace App\Models;

use App\Concerns\HasBilingualName;
use App\Enums\MovementCategory;
use Database\Factories\MovementTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An operator-defined movement type ("Airport shuttle", "VIP transfer", …) — the
 * same principle already applied to vehicle types and services, not a fixed enum
 * in code. `category` (App\Enums\MovementCategory) is the one thing every type
 * still declares, so the rest of the app can reason about "is this an arrival leg"
 * without caring what the operator called it.
 *
 * @property int $id
 * @property int $operator_id
 * @property string $name_ar
 * @property string $name_en
 * @property string $name (accessor, see HasBilingualName)
 * @property MovementCategory $category
 * @property bool $is_active
 */
class MovementType extends Model
{
    /** @use HasFactory<MovementTypeFactory> */
    use HasBilingualName, HasFactory;

    protected $fillable = ['operator_id', 'name_ar', 'name_en', 'category', 'is_active'];

    protected function casts(): array
    {
        return [
            'category' => MovementCategory::class,
            'is_active' => 'boolean',
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
     * @return HasMany<Movement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }
}
