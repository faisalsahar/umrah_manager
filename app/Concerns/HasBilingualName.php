<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * For models that store `name_ar` and `name_en` as real columns. Adds a `name`
 * accessor that picks the current locale's version (falling back to whichever is
 * filled), so every existing `->name` read across the app keeps working unchanged.
 *
 * Also adds a `name` mutator: assigning `->name` (or mass-assigning `name` — add it
 * to $fillable to allow that) writes the same value into both language columns, so
 * every existing `Model::factory()->create(['name' => 'X'])` call site keeps working
 * without needing bilingual values everywhere. Real bilingual entry sets `name_ar`/
 * `name_en` directly instead.
 */
trait HasBilingualName
{
    /**
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => app()->getLocale() === 'ar'
                ? ($this->name_ar ?: $this->name_en)
                : ($this->name_en ?: $this->name_ar),
            set: fn (string $value): array => ['name_ar' => $value, 'name_en' => $value],
        );
    }
}
