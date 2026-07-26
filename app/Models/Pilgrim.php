<?php

namespace App\Models;

use App\Enums\AgeBand;
use App\Enums\Gender;
use Database\Factories\PilgrimFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A pilgrim is a record, not a user — they never log in. They are matched on
 * passport number (name transliteration varies by country), and carry the mahram
 * link that mixed-gender family room sharing depends on. See docs/PLAN.md §2 and §4.
 *
 * @property int $id
 * @property int $operator_id
 * @property int|null $agent_id
 * @property int|null $group_id
 * @property string $name
 * @property string $passport_number
 * @property string|null $nationality
 * @property Gender|null $gender
 * @property Carbon|null $date_of_birth
 * @property string|null $phone
 * @property string|null $visa_status
 * @property string|null $visa_number
 * @property string|null $biometric_status
 * @property string|null $mofa_number
 * @property string|null $booking_type
 * @property int|null $mahram_of_pilgrim_id
 */
class Pilgrim extends Model
{
    /** @use HasFactory<PilgrimFactory> */
    use HasFactory;

    protected $fillable = [
        'operator_id', 'agent_id', 'group_id', 'name', 'passport_number',
        'nationality', 'gender', 'date_of_birth', 'phone', 'mahram_of_pilgrim_id',
        'visa_status', 'visa_number', 'biometric_status', 'mofa_number', 'booking_type',
    ];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'date_of_birth' => 'date',
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
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * The pilgrim this one is a mahram/family dependant of, if any.
     *
     * @return BelongsTo<Pilgrim, $this>
     */
    public function mahramOf(): BelongsTo
    {
        return $this->belongsTo(Pilgrim::class, 'mahram_of_pilgrim_id');
    }

    /**
     * @return HasMany<HotelStay, $this>
     */
    public function hotelStays(): HasMany
    {
        return $this->hasMany(HotelStay::class);
    }

    /**
     * @return BelongsToMany<Movement, $this>
     */
    public function movements(): BelongsToMany
    {
        return $this->belongsToMany(Movement::class, 'movement_pilgrim')->withTimestamps();
    }

    /**
     * Where this pilgrim is right now: the hotel of a stay whose date range
     * covers today. Null if nothing is currently in progress — including a
     * pilgrim who hasn't travelled yet, which is a perfectly normal state for
     * one added to the system ahead of their trip.
     */
    public function currentHotelStay(): ?HotelStay
    {
        $today = today();

        return $this->hotelStays
            ->first(fn (HotelStay $stay): bool => $stay->check_in->lte($today) && $stay->check_out->gte($today));
    }

    /**
     * The next movement still ahead of this pilgrim, soonest first — null if
     * every movement on file is already in the past, or none exist yet.
     */
    public function nextMovement(): ?Movement
    {
        return $this->movements
            ->filter(fn (Movement $movement): bool => $movement->movement_at?->isFuture() ?? false)
            ->sortBy('movement_at')
            ->first();
    }

    /**
     * The fare band (adult/child/infant) this pilgrim falls into by age, on the
     * given date (defaulting to today). Null if no date of birth is on file — not
     * every pilgrim record is complete before travel, especially ones entered from
     * an aggregate headcount rather than individual passport data.
     */
    public function ageBand(?Carbon $asOf = null): ?AgeBand
    {
        if ($this->date_of_birth === null) {
            return null;
        }

        return AgeBand::forAge($this->date_of_birth, $asOf);
    }
}
