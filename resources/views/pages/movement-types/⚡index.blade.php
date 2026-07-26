<?php

use App\Enums\MovementCategory;
use App\Enums\OrganizationType;
use App\Livewire\Concerns\GuardsDestructiveDeletes;
use App\Models\MovementType;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Movement Types')] class extends Component {
    use GuardsDestructiveDeletes;

    public ?int $editingId = null;

    public string $newNameAr = '';

    public string $newNameEn = '';

    public string $newCategory = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->currentOrganization?->type === OrganizationType::Operator, 403,
            __('Only an operator manages its movement types. Switch to your operator organization first.'));
    }

    public function getMovementTypesProperty()
    {
        return MovementType::where('operator_id', Auth::user()->current_organization_id)
            ->withCount('movements')
            ->orderBy('category')
            ->orderBy('name_en')
            ->get();
    }

    public function edit(int $movementTypeId): void
    {
        $movementType = MovementType::where('operator_id', Auth::user()->current_organization_id)->find($movementTypeId);
        abort_if($movementType === null, 404);

        $this->editingId = $movementType->id;
        $this->newNameAr = $movementType->name_ar;
        $this->newNameEn = $movementType->name_en;
        $this->newCategory = $movementType->category->value;
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'newNameAr', 'newNameEn', 'newCategory']);
    }

    public function saveMovementType(): void
    {
        $validated = $this->validate([
            'newNameAr' => ['required', 'string', 'max:255'],
            'newNameEn' => ['required', 'string', 'max:255'],
            'newCategory' => ['required', new \Illuminate\Validation\Rules\Enum(MovementCategory::class)],
        ]);

        if ($this->editingId) {
            $movementType = MovementType::where('operator_id', Auth::user()->current_organization_id)->find($this->editingId);
            abort_if($movementType === null, 404);
            $movementType->update([
                'name_ar' => $validated['newNameAr'],
                'name_en' => $validated['newNameEn'],
                'category' => $validated['newCategory'],
            ]);
            \Flux\Flux::toast(text: __('Movement type updated.'), variant: 'success');
        } else {
            MovementType::create([
                'operator_id' => Auth::user()->current_organization_id,
                'name_ar' => $validated['newNameAr'],
                'name_en' => $validated['newNameEn'],
                'category' => $validated['newCategory'],
                'is_active' => true,
            ]);
            \Flux\Flux::toast(text: __('Movement type added.'), variant: 'success');
        }

        $this->reset(['editingId', 'newNameAr', 'newNameEn', 'newCategory']);
    }

    public function toggleActive(int $movementTypeId): void
    {
        $movementType = MovementType::where('operator_id', Auth::user()->current_organization_id)->find($movementTypeId);

        abort_if($movementType === null, 404);

        $movementType->update(['is_active' => ! $movementType->is_active]);
    }

    public function deleteConfirmed(): void
    {
        $this->guardedDelete(function (int $id): void {
            $movementType = MovementType::where('operator_id', Auth::user()->current_organization_id)->find($id);
            abort_if($movementType === null, 404);
            $movementType->delete();
        });
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header
        :title="__('Movement Types')"
        :subtitle="__('The named transport legs you use every day — arrival, inter-city, ziyarat, departure — each tagged with a category so reports still make sense.')"
    />

    <x-ui.card :padded="false">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->movementTypes as $movementType)
                    <flux:table.row wire:key="movement-type-{{ $movementType->id }}">
                        <flux:table.cell class="font-medium" :label="__('Name')">{{ $movementType->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Category')"><flux:badge size="sm">{{ $movementType->category->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell :label="__('Status')">
                            <flux:badge size="sm" :color="$movementType->is_active ? 'lime' : 'zinc'">
                                {{ $movementType->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:button size="sm" variant="ghost" wire:click="edit({{ $movementType->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="toggleActive({{ $movementType->id }})">
                                {{ $movementType->is_active ? __('Deactivate') : __('Activate') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="confirmDelete({{ $movementType->id }}, {{ $movementType->movements_count }})">
                                {{ __('Delete') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <x-ui.empty-state icon="truck" :title="__('No movement types yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.card>

    <x-ui.card>
        <flux:heading size="lg" class="mb-4">{{ $editingId ? __('Edit movement type') : __('New movement type') }}</flux:heading>
        <form wire:submit="saveMovementType" class="flex flex-wrap items-end gap-4">
            <flux:input wire:model="newNameAr" :label="__('Name (Arabic)')" class="max-w-48" />
            <flux:input wire:model="newNameEn" :label="__('Name (English)')" class="max-w-48" />
            <flux:select wire:model="newCategory" :label="__('Category')" class="max-w-48">
                <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                @foreach (\App\Enums\MovementCategory::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary">{{ $editingId ? __('Save') : __('Create') }}</flux:button>
            @if ($editingId)
                <flux:button variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
            @endif
        </form>
    </x-ui.card>

    <x-ui.confirm-delete-modal :deleting-id="$deletingId" :deleting-dependents="$deletingDependents" :math-a="$mathA" :math-b="$mathB" />
</div>
