<?php

use App\Enums\Gender;
use App\Enums\PilgrimImportField;
use App\Models\Group;
use App\Models\PilgrimImportMapping;
use App\Services\PilgrimImport\IncompatibleExcelFileException;
use App\Services\PilgrimImport\PilgrimExcelFile;
use App\Services\PilgrimImport\PilgrimImportService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Import Pilgrims')] class extends Component {
    use WithFileUploads;

    #[Locked]
    public int $groupId;

    /** upload | map | review */
    public string $step = 'upload';

    public $file = null;

    /** @var list<string> */
    public array $headers = [];

    /** @var list<array<string, string>> */
    public array $rows = [];

    /** @var array<string, string> Excel header => PilgrimImportField value */
    public array $mapping = [];

    /** @var list<array{data: array<string, mixed>, errors: list<string>}> */
    public array $reviewRows = [];

    public bool $mappingWasReused = false;

    public ?string $incompatibleError = null;

    public function mount(Group $group): void
    {
        abort_unless(Auth::user()->belongsToOrganization($group->operator), 403);

        $this->groupId = $group->id;
    }

    public function getGroupProperty()
    {
        return Group::findOrFail($this->groupId);
    }

    public function getTargetFieldsProperty()
    {
        return PilgrimImportField::cases();
    }

    public function getValidRowCountProperty(): int
    {
        return count(array_filter($this->reviewRows, fn (array $r): bool => count($r['errors']) === 0));
    }

    public function getInvalidRowCountProperty(): int
    {
        return count($this->reviewRows) - $this->validRowCount;
    }

    /**
     * Fires automatically once Livewire finishes receiving the uploaded file.
     */
    public function updatedFile(): void
    {
        $this->incompatibleError = null;

        $this->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']]);

        try {
            $parsed = PilgrimExcelFile::fromPath($this->file->getRealPath());
        } catch (IncompatibleExcelFileException $e) {
            $this->incompatibleError = $e->getMessage();
            $this->reset('file');

            return;
        }

        $this->headers = $parsed->headers;
        $this->rows = $parsed->rows;

        $saved = PilgrimImportMapping::where('operator_id', $this->group->operator_id)
            ->where('headers_signature', PilgrimImportMapping::signatureFor($this->headers))
            ->first();

        if ($saved) {
            // Same source system as a previous import (identical headers) —
            // skip straight to review instead of asking to map columns again.
            $this->mapping = $saved->mapping;
            $this->mappingWasReused = true;
            $this->buildReview();
            $this->step = 'review';

            return;
        }

        foreach ($this->headers as $header) {
            $guess = PilgrimImportField::guessFromHeader($header);
            $this->mapping[$header] = $guess?->value ?? PilgrimImportField::Ignore->value;
        }

        $this->step = 'map';
    }

    public function confirmMapping(): void
    {
        $mappedFields = array_values($this->mapping);
        $missing = array_filter(
            PilgrimImportField::required(),
            fn (PilgrimImportField $field): bool => ! in_array($field->value, $mappedFields, true),
        );

        if (count($missing) > 0) {
            $this->addError('mapping', __('Map at least: :fields', [
                'fields' => implode(', ', array_map(fn (PilgrimImportField $f): string => $f->label(), $missing)),
            ]));

            return;
        }

        PilgrimImportMapping::updateOrCreate(
            [
                'operator_id' => $this->group->operator_id,
                'headers_signature' => PilgrimImportMapping::signatureFor($this->headers),
            ],
            ['mapping' => $this->mapping],
        );

        $this->buildReview();
        $this->step = 'review';
    }

    private function buildReview(): void
    {
        $this->reviewRows = app(PilgrimImportService::class)->preview($this->rows, $this->mapping);
    }

    /**
     * Manually fill in a value the source file didn't provide — gender is the
     * common case, since many real exports omit it entirely.
     */
    public function fixRowField(int $index, string $field, string $value): void
    {
        $this->reviewRows[$index]['data'][$field] = $value;
        $this->reviewRows[$index]['errors'] = app(PilgrimImportService::class)->validateRow($this->reviewRows[$index]['data']);
    }

    public function backToMapping(): void
    {
        $this->step = 'map';
    }

    public function commitImport(): void
    {
        $validRows = array_values(array_filter($this->reviewRows, fn (array $r): bool => count($r['errors']) === 0));

        if (count($validRows) === 0) {
            \Flux\Flux::toast(text: __('No valid rows to import — fix the errors first.'), variant: 'danger');

            return;
        }

        $count = app(PilgrimImportService::class)->commit(
            $this->group->operator,
            array_map(fn (array $r): array => $r['data'], $validRows),
            $this->group->id,
        );

        \Flux\Flux::toast(text: __(':count pilgrim(s) imported.', ['count' => $count]), variant: 'success');
        $this->redirect(route('groups.show', $this->group), navigate: true);
    }

    public function startOver(): void
    {
        $this->reset(['step', 'file', 'headers', 'rows', 'mapping', 'reviewRows', 'mappingWasReused', 'incompatibleError']);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('Import Pilgrims')" :subtitle="__(':group — upload a pilgrim list instead of entering each one by hand.', ['group' => $this->group->name])" />

    @if ($step === 'upload')
        <x-ui.card class="max-w-xl">
            <flux:heading size="lg" class="mb-4">{{ __('Upload a file') }}</flux:heading>
            <form class="flex flex-col gap-4">
                <flux:input type="file" wire:model="file" :label="__('Excel or CSV file')" accept=".xlsx,.xls,.csv" />
                <flux:text class="text-xs text-zinc-500">
                    {{ __('The first row must be column headers. If this operator has imported from the same source before, matching columns are mapped automatically.') }}
                </flux:text>
            </form>
        </x-ui.card>
    @endif

    @if ($step === 'map')
        <x-ui.card :padded="false">
            <flux:heading size="lg" class="p-6 pb-0">{{ __('Map columns') }}</flux:heading>
            <flux:text class="px-6 text-xs text-zinc-500">
                {{ __('Match each column from your file to a field here. This mapping is remembered for next time.') }}
            </flux:text>
            @error('mapping')
                <flux:callout variant="danger" class="mx-6 mt-4" icon="exclamation-triangle" :heading="$message" />
            @enderror
            <flux:table class="mt-3">
                <flux:table.columns>
                    <flux:table.column>{{ __('Column in your file') }}</flux:table.column>
                    <flux:table.column>{{ __('Maps to') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($headers as $header)
                        <flux:table.row wire:key="map-{{ $loop->index }}">
                            <flux:table.cell class="font-medium" :label="__('Column in your file')">{{ $header }}</flux:table.cell>
                            <flux:table.cell :label="__('Maps to')">
                                <flux:select wire:model="mapping.{{ $header }}">
                                    @foreach ($this->targetFields as $targetField)
                                        <flux:select.option value="{{ $targetField->value }}">{{ $targetField->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
            <div class="flex justify-end gap-3 p-6 pt-4">
                <flux:button variant="ghost" wire:click="startOver">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="confirmMapping">{{ __('Continue') }}</flux:button>
            </div>
        </x-ui.card>
    @endif

    @if ($step === 'review')
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-ui.stat icon="check-circle" color="lime" :label="__('Ready to import')" :value="$this->validRowCount" />
            <x-ui.stat icon="exclamation-triangle" color="amber" :label="__('Needs a fix')" :value="$this->invalidRowCount" />
        </div>

        @if ($mappingWasReused)
            <flux:callout variant="secondary" icon="check-circle" :heading="__('Reused the column mapping saved from a previous import for this operator.')" />
        @endif

        <x-ui.card :padded="false">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Passport') }}</flux:table.column>
                    <flux:table.column>{{ __('Gender') }}</flux:table.column>
                    <flux:table.column minor>{{ __('Nationality') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($reviewRows as $index => $reviewRow)
                        <flux:table.row wire:key="review-{{ $index }}">
                            <flux:table.cell class="font-medium" :label="__('Name')">{{ $reviewRow['data']['name'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell :label="__('Passport')">{{ $reviewRow['data']['passport_number'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell :label="__('Gender')">
                                @if (blank($reviewRow['data']['gender'] ?? null) || \App\Services\PilgrimImport\PilgrimImportService::normalizeGender((string) ($reviewRow['data']['gender'] ?? '')) === null)
                                    <flux:select wire:change="fixRowField({{ $index }}, 'gender', $event.target.value)" class="max-w-32">
                                        <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                                        @foreach (Gender::cases() as $case)
                                            <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                @else
                                    {{ ucfirst($reviewRow['data']['gender']) }}
                                @endif
                            </flux:table.cell>
                            <flux:table.cell minor :label="__('Nationality')">{{ $reviewRow['data']['nationality'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell :label="__('Status')">
                                @if (count($reviewRow['errors']) === 0)
                                    <flux:badge size="sm" color="lime">{{ __('Ready') }}</flux:badge>
                                @else
                                    <flux:tooltip :content="implode(' ', $reviewRow['errors'])">
                                        <flux:badge size="sm" color="amber">{{ __('Needs a fix') }}</flux:badge>
                                    </flux:tooltip>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </x-ui.card>

        <div class="flex justify-end gap-3">
            <flux:button variant="ghost" wire:click="startOver">{{ __('Cancel') }}</flux:button>
            <flux:button variant="ghost" wire:click="backToMapping">{{ __('Back to mapping') }}</flux:button>
            <flux:button variant="primary" wire:click="commitImport" :disabled="$this->validRowCount === 0">
                {{ __('Import :count pilgrim(s)', ['count' => $this->validRowCount]) }}
            </flux:button>
        </div>
    @endif

    <flux:modal :show="$incompatibleError !== null" @close="$set('incompatibleError', null)" focusable class="max-w-md">
        @if ($incompatibleError !== null)
            <div class="flex flex-col gap-4">
                <div>
                    <flux:heading size="lg">{{ __('This file can\'t be imported') }}</flux:heading>
                    <flux:subheading>{{ $incompatibleError }}</flux:subheading>
                </div>
                <div class="flex justify-end">
                    <flux:modal.close>
                        <flux:button variant="primary">{{ __('Okay') }}</flux:button>
                    </flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
