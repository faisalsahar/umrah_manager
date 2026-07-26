<?php

namespace App\Models;

use App\Enums\ServiceType;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A row in the operator's "other services" catalogue (visa, ziyarat, catering, …).
 * Unlike hotels and transport, these carry no date/capacity inventory — see
 * docs/PLAN.md §6 — so a new one is just a new row, not a migration.
 *
 * @property int $id
 * @property int $operator_id
 * @property ServiceType $type
 * @property string $name
 * @property int|null $default_sale_price_cents
 * @property string $currency
 * @property bool $is_active
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected $fillable = ['operator_id', 'type', 'name', 'default_sale_price_cents', 'currency', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
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
     * @return HasMany<OrderLine, $this>
     */
    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }
}
