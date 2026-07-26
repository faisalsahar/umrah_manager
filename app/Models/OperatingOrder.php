<?php

namespace App\Models;

use App\Enums\OperatingOrderStatus;
use App\Enums\TripMode;
use Database\Factories\OperatingOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * The operating order (أمر التشغيل) — the document that ties everything together:
 * the operator, the agent/client who sent the pilgrims, the group, and (via
 * order_lines/movements/hotel_stays) the hotels and transport. Every other module
 * hangs off this one. See docs/PLAN.md §3.
 *
 * Pilgrim counts (adults/children/infants) are manually entered here rather than
 * always derived from actual Pilgrim records, because a real order is often opened
 * before individual pilgrims are known — see App\Enums\AgeBand for the computed
 * equivalent once pilgrims exist.
 *
 * @property int $id
 * @property string $reference_no
 * @property int $operator_id
 * @property int|null $agent_id
 * @property int|null $group_id
 * @property int|null $entered_by_user_id
 * @property OperatingOrderStatus $status
 * @property int|null $adults_count
 * @property int|null $children_count
 * @property int|null $infants_count
 * @property Carbon|null $arrival_date
 * @property TripMode|null $arrival_trip_mode
 * @property int|null $arrival_city_id
 * @property int|null $arrival_port_location_id
 * @property string|null $arrival_carrier
 * @property string|null $arrival_flight_no
 * @property string|null $arrival_time
 * @property TripMode|null $departure_trip_mode
 * @property int|null $departure_city_id
 * @property int|null $departure_port_location_id
 * @property string|null $departure_carrier
 * @property string|null $departure_flight_no
 * @property Carbon|null $departure_date
 * @property string|null $departure_time
 * @property string|null $travel_mode
 * @property string|null $notes
 * @property Carbon|null $approved_at
 */
class OperatingOrder extends Model
{
    /** @use HasFactory<OperatingOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'reference_no', 'operator_id', 'agent_id', 'group_id', 'entered_by_user_id', 'status',
        'adults_count', 'children_count', 'infants_count',
        'arrival_date', 'arrival_trip_mode', 'arrival_city_id', 'arrival_port_location_id',
        'arrival_carrier', 'arrival_flight_no', 'arrival_time',
        'departure_date', 'departure_trip_mode', 'departure_city_id', 'departure_port_location_id',
        'departure_carrier', 'departure_flight_no', 'departure_time',
        'travel_mode', 'notes', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OperatingOrderStatus::class,
            'arrival_date' => 'date',
            'arrival_trip_mode' => TripMode::class,
            'departure_date' => 'date',
            'departure_trip_mode' => TripMode::class,
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OperatingOrder $order): void {
            if (blank($order->reference_no)) {
                $order->reference_no = static::generateReferenceNo();
            }
        });
    }

    /**
     * A short, unique, human-friendly reference (e.g. "OP-K7P2QX").
     */
    public static function generateReferenceNo(): string
    {
        do {
            $code = 'OP-'.Str::upper(Str::random(6));
        } while (static::where('reference_no', $code)->exists());

        return $code;
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'operator_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'agent_id');
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function arrivalCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'arrival_city_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function arrivalPort(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'arrival_port_location_id');
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function departureCity(): BelongsTo
    {
        return $this->belongsTo(City::class, 'departure_city_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function departurePort(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'departure_port_location_id');
    }

    /**
     * @return HasMany<Movement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    /**
     * @return HasMany<HotelStay, $this>
     */
    public function hotelStays(): HasMany
    {
        return $this->hasMany(HotelStay::class);
    }

    /**
     * @return HasMany<OrderLine, $this>
     */
    public function orderLines(): HasMany
    {
        return $this->hasMany(OrderLine::class);
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /**
     * The central visibility rule (docs/PLAN.md §3, §7): the operator sees every
     * order it operates; an agent sees only the orders it sent in. This is the one
     * place that rule is expressed — callers should always filter through this
     * rather than reaching for operator_id/agent_id directly.
     *
     * @param  Builder<OperatingOrder>  $query
     * @return Builder<OperatingOrder>
     */
    public function scopeVisibleTo(Builder $query, Organization $organization): Builder
    {
        return $query->where(function (Builder $query) use ($organization): void {
            $query->where('operator_id', $organization->id)
                ->orWhere('agent_id', $organization->id);
        });
    }

    /**
     * Total sale price across every line on this order, in minor currency units.
     */
    public function totalSaleCents(): int
    {
        return (int) $this->orderLines->sum(fn (OrderLine $line): int => $line->sale_price_cents * $line->quantity);
    }

    /**
     * Total purchase (cost) price across every line on this order.
     */
    public function totalPurchaseCents(): int
    {
        return (int) $this->orderLines->sum(fn (OrderLine $line): int => $line->purchase_price_cents * $line->quantity);
    }

    /**
     * The operator's margin on this order: sale minus purchase, across every line.
     */
    public function marginCents(): int
    {
        return $this->totalSaleCents() - $this->totalPurchaseCents();
    }

    /**
     * Total pilgrim count across the three fare bands, or null if none were entered.
     */
    public function totalPilgrimCount(): ?int
    {
        if ($this->adults_count === null && $this->children_count === null && $this->infants_count === null) {
            return null;
        }

        return ($this->adults_count ?? 0) + ($this->children_count ?? 0) + ($this->infants_count ?? 0);
    }
}
