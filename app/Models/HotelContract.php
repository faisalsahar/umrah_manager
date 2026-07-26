<?php

namespace App\Models;

use App\Enums\HotelContractType;
use Carbon\CarbonInterface;
use Database\Factories\HotelContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * How the operator holds inventory with a hotel for a date range. `release_days` is
 * the allotment release deadline — days before check-in that unsold rooms revert to
 * the hotel. A real, money-losing date; see docs/PLAN.md §4.
 *
 * @property int $id
 * @property int $hotel_id
 * @property int $operator_id
 * @property HotelContractType $contract_type
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property int|null $release_days
 * @property string $currency
 * @property bool $is_active
 */
class HotelContract extends Model
{
    /** @use HasFactory<HotelContractFactory> */
    use HasFactory;

    protected $fillable = [
        'hotel_id', 'operator_id', 'contract_type', 'starts_on', 'ends_on',
        'release_days', 'currency', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'contract_type' => HotelContractType::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Hotel, $this>
     */
    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'operator_id');
    }

    /**
     * @return HasMany<ContractRate, $this>
     */
    public function rates(): HasMany
    {
        return $this->hasMany(ContractRate::class);
    }

    /**
     * @return HasMany<ContractAllotment, $this>
     */
    public function allotments(): HasMany
    {
        return $this->hasMany(ContractAllotment::class);
    }

    /**
     * The date the allotment must be released back to the hotel if unsold, or null
     * for contracts with no release deadline (free sale / on request).
     */
    public function releaseDeadline(): ?CarbonInterface
    {
        return $this->release_days === null
            ? null
            : $this->starts_on->copy()->subDays($this->release_days);
    }
}
