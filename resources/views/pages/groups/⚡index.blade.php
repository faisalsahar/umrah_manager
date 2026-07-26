<?php

use App\Models\Group;
use App\Models\Organization;
use App\Enums\OrganizationType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Groups')] class extends Component {
    public string $newName = '';

    public string $newAgentId = '';

    public string $newArrivalDate = '';

    public string $newDepartureDate = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->currentOrganization?->type === OrganizationType::Operator, 403,
            __('Only an operator manages groups. Switch to your operator organization first.'));
    }

    public function getGroupsProperty()
    {
        return Group::where('operator_id', Auth::user()->current_organization_id)
            ->withCount('pilgrims')
            ->with('agent')
            ->orderByDesc('arrival_date')
            ->get();
    }

    public function getAgentsProperty()
    {
        return Organization::ofType(OrganizationType::Agent)->orderBy('name_en')->get();
    }

    public function createGroup(): void
    {
        $validated = $this->validate([
            'newName' => ['required', 'string', 'max:255'],
            'newAgentId' => ['nullable', 'integer', Rule::exists('organizations', 'id')->where('type', OrganizationType::Agent->value)],
            'newArrivalDate' => ['nullable', 'date'],
            'newDepartureDate' => ['nullable', 'date', 'after_or_equal:newArrivalDate'],
        ]);

        Group::create([
            'operator_id' => Auth::user()->current_organization_id,
            'agent_id' => $validated['newAgentId'] ?: null,
            'name' => $validated['newName'],
            'arrival_date' => $validated['newArrivalDate'] ?: null,
            'departure_date' => $validated['newDepartureDate'] ?: null,
        ]);

        $this->reset(['newName', 'newAgentId', 'newArrivalDate', 'newDepartureDate']);
        \Flux\Flux::toast(text: __('Group created.'), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('Groups')" :subtitle="__('The operational unit inside the Kingdom — a bus holds a group, a rooming list covers a group.')" />

    <x-ui.card :padded="false">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Agent') }}</flux:table.column>
                <flux:table.column minor>{{ __('Pilgrims') }}</flux:table.column>
                <flux:table.column>{{ __('Arrival') }}</flux:table.column>
                <flux:table.column minor>{{ __('Departure') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->groups as $group)
                    <flux:table.row wire:key="group-{{ $group->id }}">
                        <flux:table.cell class="font-medium" :label="__('Name')">{{ $group->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Agent')">{{ $group->agent?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Pilgrims')">{{ $group->pilgrims_count }}</flux:table.cell>
                        <flux:table.cell :label="__('Arrival')">{{ $group->arrival_date?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Departure')">{{ $group->departure_date?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('groups.show', $group)" wire:navigate>{{ __('View pilgrims') }}</flux:link>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <x-ui.empty-state icon="users" :title="__('No groups yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.card>

    <x-ui.card>
        <flux:heading size="lg" class="mb-4">{{ __('New group') }}</flux:heading>
        <form wire:submit="createGroup" class="flex flex-wrap items-end gap-4">
            <flux:input wire:model="newName" :label="__('Name')" placeholder="Group SD-2026-014" class="max-w-56" />
            <flux:select wire:model="newAgentId" :label="__('Agent')" class="max-w-56">
                <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                @foreach ($this->agents as $agent)
                    <flux:select.option value="{{ $agent->id }}">{{ $agent->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="date" wire:model="newArrivalDate" :label="__('Arrival')" />
            <flux:input type="date" wire:model="newDepartureDate" :label="__('Departure')" />
            <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
        </form>
    </x-ui.card>
</div>
