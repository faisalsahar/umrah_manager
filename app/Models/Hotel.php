<?php

namespace App\Models;

use App\Concerns\HasBilingualName;
use App\Enums\HotelCity;
use Database\Factories\HotelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * `deleted_at` (via SoftDeletes) gives hotels archive/restore semantics instead of
 * a hand-rolled "is_archived" flag — a hotel is archived by deleting it and
 * restored with `->restore()`. `confirmed_at`/`confirmed_by_user_id` are a
 * lightweight moderation step: an entry can be added, then explicitly confirmed by
 * an admin before it's fully trusted.
 *
 * @property int $id
 * @property int $operator_id
 * @property int|null $managed_by_organization_id
 * @property string $name_ar
 * @property string $name_en
 * @property string $name (accessor, see HasBilingualName)
 * @property HotelCity $city
 * @property int|null $stars
 * @property bool $is_active
 * @property Carbon|null $confirmed_at
 * @property int|null $confirmed_by_user_id
 * @property Carbon|null $deleted_at
 */
class Hotel extends Model
{
    /** @use HasFactory<HotelFactory> */
    use HasBilingualName, HasFactory, SoftDeletes;

    protected $fillable = ['operator_id', 'managed_by_organization_id', 'name', 'name_ar', 'name_en', 'city', 'stars', 'is_active'];

    protected function casts(): array
    {
        return [
            'city' => HotelCity::class,
            'is_active' => 'boolean',
            'confirmed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_user_id');
    }

    /**
     * @return HasMany<RoomType, $this>
     */
    public function roomTypes(): HasMany
    {
        return $this->hasMany(RoomType::class);
    }

    /**
     * @return HasMany<HotelContract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(HotelContract::class);
    }

    /**
     * @return HasMany<HotelStay, $this>
     */
    public function stays(): HasMany
    {
        return $this->hasMany(HotelStay::class);
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function confirm(User $user): void
    {
        $this->forceFill(['confirmed_at' => now(), 'confirmed_by_user_id' => $user->id])->save();
    }

    public function unconfirm(): void
    {
        $this->forceFill(['confirmed_at' => null, 'confirmed_by_user_id' => null])->save();
    }
}
