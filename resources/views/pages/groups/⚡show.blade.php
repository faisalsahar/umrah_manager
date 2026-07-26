<?php

use App\Enums\Gender;
use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Group')] class extends Component {
    #[Locked]
    public int $groupId;

    public string $name = '';

    public string $passportNumber = '';

    public string $nationality = '';

    public string $gender = '';

    public string $mahramOfPilgrimId = '';

    public function mount(Group $group): void
    {
        abort_unless(Auth::user()->belongsToOrganization($group->operator), 403);

        $this->groupId = $group->id;
    }

    public function getGroupProperty()
    {
        return Group::with(['agent', 'operator', 'pilgrims.mahramOf'])->findOrFail($this->groupId);
    }

    public function addPilgrim(): void
    {
        $group = $this->group;

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'passportNumber' => ['required', 'string', 'max:32'],
            'nationality' => ['nullable', 'string', 'size:2'],
            'gender' => ['required', new \Illuminate\Validation\Rules\Enum(Gender::class)],
            // Scoped to this group's own pilgrims — otherwise a tampered request could
            // link to another operator's pilgrim and leak their name onto this screen.
            'mahramOfPilgrimId' => ['nullable', 'integer', Rule::exists('pilgrims', 'id')->where('group_id', $group->id)],
        ]);

        $group->pilgrims()->create([
            'operator_id' => $group->operator_id,
            'agent_id' => $group->agent_id,
            'name' => $validated['name'],
            'passport_number' => $validated['passportNumber'],
            'nationality' => $validated['nationality'] ?: null,
            'gender' => $validated['gender'],
            'mahram_of_pilgrim_id' => $validated['mahramOfPilgrimId'] ?: null,
        ]);

        $this->reset(['name', 'passportNumber', 'nationality', 'gender', 'mahramOfPilgrimId']);
        \Flux\Flux::toast(text: __('Pilgrim added.'), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="$this->group->name">
        <x-slot name="subtitle">
            {{ $this->group->operator->name }}
            @if ($this->group->agent)
                &middot; {{ __('Agent') }}: {{ $this->group->agent->name }}
            @endif
        </x-slot>
        <x-slot name="actions">
            <flux:button :href="route('groups.import', $this->group)" variant="ghost" icon="arrow-up-tray" wire:navigate>
                {{ __('Import from Excel') }}
            </flux:button>
        </x-slot>
    </x-ui.page-header>

    <x-ui.card :padded="false">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Passport') }}</flux:table.column>
                <flux:table.column minor>{{ __('Nationality') }}</flux:table.column>
                <flux:table.column>{{ __('Gender') }}</flux:table.column>
                <flux:table.column minor>{{ __('Travels with') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->group->pilgrims as $pilgrim)
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
                        <flux:table.cell minor :label="__('Travels with')">{{ $pilgrim->mahramOf?->name ?? '—' }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5">
                            <x-ui.empty-state icon="user" :title="__('No pilgrims in this group yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.card>

    <x-ui.card>
        <flux:heading size="lg" class="mb-4">{{ __('Add a pilgrim') }}</flux:heading>
        <form wire:submit="addPilgrim" class="flex flex-wrap items-end gap-4">
            <flux:input wire:model="name" :label="__('Name')" class="max-w-56" />
            <flux:input wire:model="passportNumber" :label="__('Passport number')" class="max-w-40" />
            <flux:input wire:model="nationality" :label="__('Nationality')" placeholder="SD" class="max-w-24" />
            <flux:select wire:model="gender" :label="__('Gender')" class="max-w-40">
                <flux:select.option value="">{{ __('Choose') }}</flux:select.option>
                @foreach (\App\Enums\Gender::cases() as $case)
                    <flux:select.option value="{{ $case->value }}">{{ $case->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="mahramOfPilgrimId" :label="__('Travels with (mahram)')" class="max-w-56">
                <flux:select.option value="">{{ __('— None —') }}</flux:select.option>
                @foreach ($this->group->pilgrims as $existing)
                    <flux:select.option value="{{ $existing->id }}">{{ $existing->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
        </form>
    </x-ui.card>
</div>
