<?php

use App\Enums\OrganizationType;
use App\Enums\ServiceType;
use App\Livewire\Concerns\GuardsDestructiveDeletes;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Services')] class extends Component {
    use GuardsDestructiveDeletes;

    public ?int $editingId = null;

    public string $newName = '';

    public string $newType = '';

    public string $newPrice = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->currentOrganization?->type === OrganizationType::Operator, 403,
            __('Only an operator manages its services catalogue. Switch to your operator organization first.'));
    }

    public function getServicesProperty()
    {
        return Service::where('operator_id', Auth::user()->current_organization_id)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    public function edit(int $serviceId): void
    {
        $service = Service::where('operator_id', Auth::user()->current_organization_id)->find($serviceId);

        abort_if($service === null, 404);

        $this->editingId = $service->id;
        $this->newName = $service->name;
        $this->newType = $service->type->value;
        $this->newPrice = $service->default_sale_price_cents !== null
            ? (string) ($service->default_sale_price_cents / 100)
            : '';
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'newName', 'newType', 'newPrice']);
    }

    public function saveService(): void
    {
        $validated = $this->validate([
            'newName' => ['required', 'string', 'max:255'],
            'newType' => ['required', new \Illuminate\Validation\Rules\Enum(ServiceType::class)],
            'newPrice' => ['nullable', 'numeric', 'min:0'],
        ]);

        $attributes = [
            'type' => $validated['newType'],
            'name' => $validated['newName'],
            'default_sale_price_cents' => $validated['newPrice'] !== '' && $validated['newPrice'] !== null
                ? (int) round($validated['newPrice'] * 100)
                : null,
        ];

        if ($this->editingId) {
            $service = Service::where('operator_id', Auth::user()->current_organization_id)->find($this->editingId);
            abort_if($service === null, 404);
            $service->update($attributes);
            \Flux\Flux::toast(text: __('Service updated.'), variant: 'success');
        } else {
            Service::create(['operator_id' => Auth::user()->current_organization_id, ...$attributes]);
            \Flux\Flux::toast(text: __('Service added.'), variant: 'success');
        }

        $this->reset(['editingId', 'newName', 'newType', 'newPrice']);
    }

    public function toggleActive(int $serviceId): void
    {
        $service = Service::where('operator_id', Auth::user()->current_organization_id)->find($serviceId);

        abort_if($service === null, 404);

        $service->update(['is_active' => ! $service->is_active]);
    }

    public function deleteConfirmed(): void
    {
        $this->guardedDelete(function (int $id): void {
            $service = Service::where('operator_id', Auth::user()->current_organization_id)->find($id);
            abort_if($service === null, 404);
            $service->delete();
        });
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header
        :title="__('Services')"
        :subtitle="__('The catalogue of everything you sell beyond hotels and transport — visa, ziyarat, catering, and anything else. A new one is just a row here, no migration required.')"
    />

    <x-ui.card :padded="false">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Default sale price') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->services as $service)
                    <flux:table.row wire:key="service-{{ $service->id }}">
                        <flux:table.cell class="font-medium" :label="__('Name')">{{ $service->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Type')"><flux:badge size="sm">{{ $service->type->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell :label="__('Default sale price')">
                            {{ $service->default_sale_price_cents !== null ? number_format($service->default_sale_price_cents / 100, 2).' '.$service->currency : '—' }}
                        </flux:table.cell>
                        <flux:table.cell :label="__('Status')">
                            <flux:badge size="sm" :color="$service->is_active ? 'lime' : 'zinc'">
                                {{ $service->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell class="flex items-center gap-3">
                            <flux:button size="sm" variant="ghost" wire:click="edit({{ $service->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="toggleActive({{ $service->id }})">
                                {{ $service->is_active ? __('Deactivate') : __('Activate') }}
                            </flux:button>
                            <flux:button size="sm" variant="ghost" wire:click="confirmDelete({{ $service->id }}, {{ $service->orderLines()->count() }})">
                                {{ __('Delete') }}
                            </flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            <x-ui.empty-state icon="ticket" :title="__('No services yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.card>

    <x-ui.card>
        <flux:heading size="lg" class="mb-4">{{ $editingId ? __('Edit service') : __('New service') }}</flux:heading>
        <form wire:submit="saveService" class="flex flex-wrap items-end gap-4">
            <flux:input wire:model="newName" :label="__('Name')" placeholder="Ziyarat Tour" class="max-w-56" />
            <flux:select wire:model="newType" :label="__('Type')" class="max-w-48">
                <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                @foreach (\App\Enums\ServiceType::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="number" step="0.01" min="0" wire:model="newPrice" :label="__('Default sale price')" class="max-w-40" />
            <flux:button type="submit" variant="primary">{{ $editingId ? __('Save') : __('Create') }}</flux:button>
            @if ($editingId)
                <flux:button variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
            @endif
        </form>
    </x-ui.card>

    <x-ui.confirm-delete-modal :deleting-id="$deletingId" :deleting-dependents="$deletingDependents" :math-a="$mathA" :math-b="$mathB" />
</div>
