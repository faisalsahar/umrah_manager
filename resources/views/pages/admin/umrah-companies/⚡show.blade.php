<?php

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Umrah Company')] class extends Component {
    #[Locked]
    public int $companyId;

    public string $subscriptionExpiresAt = '';

    public bool $isActive = true;

    public string $memberCode = '';

    public string $memberRole = '';

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
     * @return list<OrganizationRole>
     */
    public function getMemberRolesProperty(): array
    {
        return OrganizationRole::availableFor(OrganizationType::Operator);
    }

    /**
     * The admin's other lever besides creating a brand-new company: drop an
     * already-registered user straight into an existing one by their onboarding
     * code. Everything else about membership (removing, re-assigning a role)
     * stays the company's own job — see the read-only roster below.
     */
    public function addMemberByCode(): void
    {
        $validated = $this->validate([
            'memberCode' => ['required', 'string'],
            'memberRole' => ['required', \Illuminate\Validation\Rule::in(array_map(fn (OrganizationRole $role): string => $role->value, OrganizationRole::availableFor(OrganizationType::Operator)))],
        ]);

        $newMember = User::where('onboarding_code', strtoupper($validated['memberCode']))->first();

        if (! $newMember) {
            $this->addError('memberCode', __('No user was found with that onboarding code.'));

            return;
        }

        $this->company->addMember($newMember, OrganizationRole::from($validated['memberRole']));

        $this->reset(['memberCode', 'memberRole']);
        \Flux\Flux::toast(text: __(':name added to :organization.', ['name' => $newMember->name, 'organization' => $this->company->name]), variant: 'success');
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

        <form wire:submit="addMemberByCode" class="flex flex-wrap items-end gap-4 p-6 pt-4">
            <flux:input wire:model="memberCode" :label="__('Onboarding code')" placeholder="K7P2QX9M" class="max-w-48" />
            <flux:select wire:model="memberRole" :label="__('Role')" class="max-w-48">
                <flux:select.option value="">{{ __('Choose a role') }}</flux:select.option>
                @foreach ($this->memberRoles as $role)
                    <flux:select.option value="{{ $role->value }}">{{ $role->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
        </form>

        <flux:text class="px-6 pb-6 text-xs text-zinc-500">
            {{ __('Removing or re-assigning an existing member\'s role is still done by the company itself, not from here.') }}
        </flux:text>
    </x-ui.card>
</div>
