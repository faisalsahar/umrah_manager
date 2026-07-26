<?php

namespace App\Models;

use App\Concerns\HasBilingualName;
use Database\Factories\PermitTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An operator-defined permit type (e.g. a Nusuk movement permit, a ziyarat permit).
 * Not location-scoped — a permit type isn't tied to a place.
 *
 * @property int $id
 * @property int $operator_id
 * @property string $name_ar
 * @property string $name_en
 * @property string $name (accessor, see HasBilingualName)
 * @property string|null $notes
 */
class PermitType extends Model
{
    /** @use HasFactory<PermitTypeFactory> */
    use HasBilingualName, HasFactory;

    protected $fillable = ['operator_id', 'name_ar', 'name_en', 'notes'];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'operator_id');
    }
}
