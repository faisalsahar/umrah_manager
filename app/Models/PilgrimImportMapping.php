<?php

namespace App\Models;

use Database\Factories\PilgrimImportMappingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Remembers, per operator, which target field each Excel column header was
 * mapped to on a previous import — so uploading another file from the same
 * source system (identical headers, any order) can skip straight to the
 * validation/review step instead of asking the user to map columns again.
 *
 * @property int $id
 * @property int $operator_id
 * @property string $headers_signature
 * @property array<string, string> $mapping
 */
class PilgrimImportMapping extends Model
{
    /** @use HasFactory<PilgrimImportMappingFactory> */
    use HasFactory;

    protected $fillable = ['operator_id', 'headers_signature', 'mapping'];

    protected function casts(): array
    {
        return ['mapping' => 'array'];
    }

    /**
     * A stable signature for a set of Excel column headers — order-independent
     * and case/whitespace-insensitive, so the same source file re-exported
     * still matches a previously saved mapping.
     *
     * @param  list<string>  $headers
     */
    public static function signatureFor(array $headers): string
    {
        $normalized = array_map(fn (string $header): string => mb_strtolower(trim($header)), $headers);
        sort($normalized);

        return hash('sha256', implode('|', $normalized));
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'operator_id');
    }
}
