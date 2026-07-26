<?php

use App\Enums\OrganizationType;
use App\Models\Pilgrim;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('My Pilgrims')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->currentOrganization?->type === OrganizationType::Agent, 403,
            __('Only an agent searches its own pilgrims here. Switch to your agent organization first.'));
    }

    /**
     * Every pilgrim this agent has sent in, across every operator it works
     * with — scoped to agent_id, never to a specific operator's own screens,
     * since an agent's own information is exactly that: its own, regardless
     * of who operated the trip.
     */
    public function getPilgrimsProperty()
    {
        $agentId = Auth::user()->current_organization_id;

        return Pilgrim::where('agent_id', $agentId)
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('passport_number', 'like', $term)
                    ->orWhere('nationality', 'like', $term));
            })
            ->with([
                'group',
                'operator',
                'hotelStays' => fn ($query) => $query->with('hotel'),
                'movements' => fn ($query) => $query->with('movementType'),
            ])
            ->orderBy('name')
            ->paginate(20);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('My Pilgrims')" :subtitle="__('Every pilgrim you\'ve sent in, across every operator you work with — search by name, passport, or nationality.')" />

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name, passport, or nationality')" class="max-w-md" />

    <x-ui.card :padded="false">
        <flux:table :paginate="$this->pilgrims">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Passport') }}</flux:table.column>
                <flux:table.column minor>{{ __('Nationality') }}</flux:table.column>
                <flux:table.column>{{ __('Gender') }}</flux:table.column>
                <flux:table.column minor>{{ __('Operator') }}</flux:table.column>
                <flux:table.column>{{ __('Group') }}</flux:table.column>
                <flux:table.column minor>{{ __('Visa Status') }}</flux:table.column>
                <flux:table.column>{{ __('Where') }}</flux:table.column>
                <flux:table.column>{{ __('Next Movement') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->pilgrims as $pilgrim)
                    @php
                        $currentStay = $pilgrim->currentHotelStay();
                        $nextMovement = $pilgrim->nextMovement();
                    @endphp
                    <flux:table.row wire:key="pilgrim-{{ $pilgrim->id }}">
                        <flux:table.cell class="font-medium" :label="__('Name')">{{ $pilgrim->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Passport')">{{ $pilgrim->passport_number }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Nationality')">{{ $pilgrim->nationality ?? '—' }}</flux:table.cell>
                        <flux:table.cell :label="__('Gender')">
                            @if ($pilgrim->gender)
                                <flux:badge size="sm">{{ $pilgrim->gender->label() }}</flux:badge>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell minor :label="__('Operator')">{{ $pilgrim->operator->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Group')">{{ $pilgrim->group?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Visa Status')">{{ $pilgrim->visa_status ?? '—' }}</flux:table.cell>
                        <flux:table.cell :label="__('Where')">
                            @if ($currentStay)
                                <flux:badge size="sm" color="lime">{{ $currentStay->hotel->name }}</flux:badge>
                            @else
                                <span class="text-zinc-500">{{ __('Not yet travelled') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell :label="__('Next Movement')">
                            @if ($nextMovement)
                                {{ $nextMovement->movementType->name }} &middot; {{ $nextMovement->movement_at->format('Y-m-d H:i') }}
                            @else
                                <span class="text-zinc-500">—</span>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="9">
                            <x-ui.empty-state icon="magnifying-glass" :title="$search !== '' ? __('No pilgrims match that search.') : __('No pilgrims yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.card>
</div>
