<?php

use App\Enums\OrganizationType;
use App\Livewire\Concerns\GuardsDestructiveDeletes;
use App\Models\PermitType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Permit Types')] class extends Component {
    use GuardsDestructiveDeletes;

    public ?int $editingId = null;

    public string $newNameAr = '';

    public string $newNameEn = '';

    public string $newNotes = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->currentOrganization?->type === OrganizationType::Operator, 403,
            __('Only an operator manages its permit types. Switch to your operator organization first.'));
    }

    public function getPermitTypesProperty()
    {
        return PermitType::where('operator_id', Auth::user()->current_organization_id)
            ->orderBy('name_en')
            ->get();
    }

    public function edit(int $permitTypeId): void
    {
        $permitType = PermitType::where('operator_id', Auth::user()->current_organization_id)->find($permitTypeId);
        abort_if($permitType === null, 404);

        $this->editingId = $permitType->id;
        $this->newNameAr = $permitType->name_ar;
        $this->newNameEn = $permitType->name_en;
        $this->newNotes = $permitType->notes ?? '';
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'newNameAr', 'newNameEn', 'newNotes']);
    }

    public function savePermitType(): void
    {
        $validated = $this->validate([
            'newNameAr' => ['required', 'string', 'max:255'],
            'newNameEn' => ['required', 'string', 'max:255'],
            'newNotes' => ['nullable', 'string', 'max:1000'],
        ]);

        $attributes = [
            'name_ar' => $validated['newNameAr'],
            'name_en' => $validated['newNameEn'],
            'notes' => $validated['newNotes'] ?: null,
        ];

        if ($this->editingId) {
            $permitType = PermitType::where('operator_id', Auth::user()->current_organization_id)->find($this->editingId);
            abort_if($permitType === null, 404);
            $permitType->update($attributes);
            \Flux\Flux::toast(text: __('Permit type updated.'), variant: 'success');
        } else {
            PermitType::create(['operator_id' => Auth::user()->current_organization_id, ...$attributes]);
            \Flux\Flux::toast(text: __('Permit type added.'), variant: 'success');
        }

        $this->reset(['editingId', 'newNameAr', 'newNameEn', 'newNotes']);
    }

    public function deleteConfirmed(): void
    {
        $this->guardedDelete(function (int $id): void {
            $permitType = PermitType::where('operator_id', Auth::user()->current_organization_id)->find($id);
            abort_if($permitType === null, 404);
            $permitType->delete();
        });
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header
        :title="__('Permit Types')"
        :subtitle="__('The permits your operation issues — Nusuk, ziyarat, or anything else — as a catalogue rather than free text.')"
    />

    <x-ui.card :padded="false">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Notes') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->permitTypes as $permitType)
                    <flux:table.row wire:key="permit-type-{{ $permitType->id }}">
                        <flux:table.cell class="font-medium" :label="__('Name')">{{ $permitType->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Notes')">{{ $permitType->notes ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:button size="sm" variant="ghost" wire:click="edit({{ $permitType->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="confirmDelete({{ $permitType->id }})">{{ __('Delete') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">
                            <x-ui.empty-state icon="identification" :title="__('No permit types yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.card>

    <x-ui.card>
        <flux:heading size="lg" class="mb-4">{{ $editingId ? __('Edit permit type') : __('New permit type') }}</flux:heading>
        <form wire:submit="savePermitType" class="flex flex-wrap items-end gap-4">
            <flux:input wire:model="newNameAr" :label="__('Name (Arabic)')" class="max-w-48" />
            <flux:input wire:model="newNameEn" :label="__('Name (English)')" class="max-w-48" />
            <flux:input wire:model="newNotes" :label="__('Notes')" class="max-w-64" />
            <flux:button type="submit" variant="primary">{{ $editingId ? __('Save') : __('Create') }}</flux:button>
            @if ($editingId)
                <flux:button variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
            @endif
        </form>
    </x-ui.card>

    <x-ui.confirm-delete-modal :deleting-id="$deletingId" :deleting-dependents="$deletingDependents" :math-a="$mathA" :math-b="$mathB" />
</div>
