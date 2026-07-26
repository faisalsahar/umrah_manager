<?php

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Umrah Company')] class extends Component {
    #[Locked]
    public int $companyId;

    public string $subscriptionExpiresAt = '';

    public bool $isActive = true;

    public function mount(Organization $umrahCompany): void
    {
        abort_unless(Auth::user()->isAdmin(), 403,
            __('Only the platform admin manages Umrah companies.'));
        abort_unless($umrahCompany->type === OrganizationType::Operator, 404);

        $this->companyId = $umrahCompany->id;
        $this->subscriptionExpiresAt = $umrahCompany->subscription_expires_at?->format('Y-m-d') ?? '';
        $this->isActive = $umrahCompany->is_active;
    }

    public function getCompanyProperty()
    {
        return Organization::findOrFail($this->companyId);
    }

    /**
     * Read-only roster — adding, removing, or re-assigning a member's role is the
     * company's own job (see pages::organizations.index), not the admin's.
     */
    public function getMembersProperty()
    {
        return $this->company->members()->orderBy('name')->get();
    }

    public function updateSubscription(): void
    {
        $validated = $this->validate([
            'subscriptionExpiresAt' => ['nullable', 'date'],
            'isActive' => ['boolean'],
        ]);

        $this->company->update([
            'subscription_expires_at' => $validated['subscriptionExpiresAt'] ?: null,
            'is_active' => $validated['isActive'],
        ]);

        \Flux\Flux::toast(text: __('Subscription updated.'), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="$this->company->name">
        <x-slot name="subtitle">
            {{ $this->company->country ?? '—' }}
            @if ($this->company->license_no)
                &middot; {{ __('License') }}: {{ $this->company->license_no }}
            @endif
        </x-slot>
        <x-slot name="actions">
            <flux:badge size="lg" :color="$this->company->is_active ? 'lime' : 'zinc'">
                {{ $this->company->is_active ? __('Active') : __('Deactivated') }}
            </flux:badge>
        </x-slot>
    </x-ui.page-header>

    <x-ui.card class="max-w-xl">
        <flux:heading size="lg" class="mb-4">{{ __('Subscription') }}</flux:heading>
        <form wire:submit="updateSubscription" class="flex flex-col gap-4">
            <flux:input type="date" wire:model="subscriptionExpiresAt" :label="__('Renewal date')" />
            <flux:switch wire:model="isActive" :label="__('Active')" />
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card :padded="false">
        <flux:heading size="lg" class="p-6 pb-0">{{ __('Members') }}</flux:heading>
        <flux:table class="mt-3">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->members as $member)
                    <flux:table.row wire:key="member-{{ $member->id }}">
                        <flux:table.cell class="font-medium" :label="__('Name')">{{ $member->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Email')">{{ $member->email }}</flux:table.cell>
                        <flux:table.cell :label="__('Role')">
                            <flux:badge size="sm">{{ OrganizationRole::from($member->pivot->role)->label() }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="3">
                            <x-ui.empty-state icon="users" :title="__('No members yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
        <flux:text class="p-6 pt-3 text-xs text-zinc-500">
            {{ __('Adding, removing, or re-assigning a member\'s role is done by the company itself, not from here.') }}
        </flux:text>
    </x-ui.card>
</div>
