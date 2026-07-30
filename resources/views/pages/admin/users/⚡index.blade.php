<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403,
            __('Only the platform admin sees the full user directory.'));
    }

    /**
     * Every user on the platform, regardless of which organization(s) they belong
     * to (or none at all) — the admin's own account is excluded, since it belongs
     * to nowhere and isn't part of the roster this screen is for. Each membership
     * carries its organization's own parent, if any (a sub-agent's parent agency),
     * so the full chain is visible in one place.
     */
    public function getUsersProperty()
    {
        return User::where('email', '!=', config('app.admin_email'))
            ->when($this->search !== '', function ($query) {
                $term = '%'.$this->search.'%';
                $query->where(fn ($q) => $q
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('onboarding_code', 'like', $term));
            })
            ->with(['organizations.parent'])
            ->orderBy('name')
            ->paginate(25);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('Users')" :subtitle="__('Every registered user on the platform, and which organization(s) they belong to.')" />

    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name, email, or onboarding code')" class="max-w-md" />

    <x-ui.card :padded="false">
        <flux:table :paginate="$this->users">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column minor>{{ __('Onboarding code') }}</flux:table.column>
                <flux:table.column>{{ __('Organizations') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row wire:key="user-{{ $user->id }}">
                        <flux:table.cell class="font-medium" :label="__('Name')">{{ $user->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Email')">{{ $user->email }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Onboarding code')">
                            <flux:badge size="sm">{{ $user->onboarding_code ?? '—' }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell :label="__('Organizations')">
                            @forelse ($user->organizations as $organization)
                                <div class="mb-1 last:mb-0">
                                    <flux:badge size="sm">{{ $organization->name }}</flux:badge>
                                    <span class="text-xs text-zinc-500">
                                        {{ \App\Enums\OrganizationRole::from($organization->pivot->role)->label() }}
                                        @if ($organization->parent)
                                            &middot; {{ __('sub-agent of :name', ['name' => $organization->parent->name]) }}
                                        @endif
                                    </span>
                                </div>
                            @empty
                                <span class="text-sm text-zinc-400">{{ __('No organization yet') }}</span>
                            @endforelse
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4">
                            <x-ui.empty-state icon="users" :title="__('No users match that search.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.card>
</div>
