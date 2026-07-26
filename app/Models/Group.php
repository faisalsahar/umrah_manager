<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The operational unit inside the Kingdom — a bus holds a group, a rooming list
 * covers a group. Distinct from an OperatingOrder (the commercial unit): one
 * order's pilgrims can span several groups, and one group can hold pilgrims sent by
 * several agents. See docs/PLAN.md §4.
 *
 * @property int $id
 * @property int $operator_id
 * @property int|null $agent_id
 * @property string $name
 * @property string|null $visa_type
 * @property Carbon|null $arrival_date
 * @property Carbon|null $departure_date
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    protected $table = 'groups';

    protected $fillable = ['operator_id', 'agent_id', 'name', 'visa_type', 'arrival_date', 'departure_date'];

    protected function casts(): array
    {
        return [
            'arrival_date' => 'date',
            'departure_date' => 'date',
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
     * @return BelongsTo<Organization, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'agent_id');
    }

    /**
     * @return HasMany<Pilgrim, $this>
     */
    public function pilgrims(): HasMany
    {
        return $this->hasMany(Pilgrim::class);
    }

    /**
     * @return HasMany<OperatingOrder, $this>
     */
    public function operatingOrders(): HasMany
    {
        return $this->hasMany(OperatingOrder::class);
    }
}
