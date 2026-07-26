<?php

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use App\Support\OperatorDefaults;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Umrah Companies')] class extends Component {
    public string $newNameAr = '';

    public string $newNameEn = '';

    public string $newCountry = '';

    public string $newLicenseNo = '';

    public string $founderCode = '';

    public function mount(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403,
            __('Only the platform admin manages Umrah companies.'));
    }

    /**
     * Every Umrah company on the platform — the admin's whole world. Everything
     * that happens inside one (groups, orders, hotels, movements, …) is the
     * company's own business, not something managed from here.
     */
    public function getCompaniesProperty()
    {
        return Organization::ofType(OrganizationType::Operator)
            ->withCount('members')
            ->orderBy('name_en')
            ->get();
    }

    /**
     * Create a new Umrah company and hand it to its founding owner in one step —
     * the "approval" is the admin entering that person's onboarding code here,
     * rather than a separate request/approve workflow.
     */
    public function createCompany(): void
    {
        $validated = $this->validate([
            'newNameAr' => ['required', 'string', 'max:255'],
            'newNameEn' => ['required', 'string', 'max:255'],
            'newCountry' => ['nullable', 'string', 'size:2'],
            'newLicenseNo' => ['nullable', 'string', 'max:255'],
            'founderCode' => ['required', 'string'],
        ]);

        $founder = User::where('onboarding_code', strtoupper($validated['founderCode']))->first();

        if (! $founder) {
            $this->addError('founderCode', __('No user was found with that onboarding code.'));

            return;
        }

        // Atomic: a failure partway through (e.g. seeding the starter catalog)
        // must not leave a half-built company — owned but missing its hotels, or
        // seeded but ownerless.
        $company = DB::transaction(function () use ($validated, $founder): Organization {
            $company = Organization::create([
                'type' => OrganizationType::Operator,
                'name_ar' => $validated['newNameAr'],
                'name_en' => $validated['newNameEn'],
                'country' => $validated['newCountry'] ?: null,
                'license_no' => $validated['newLicenseNo'] ?: null,
            ]);

            $company->addMember($founder, OrganizationRole::Owner);
            OperatorDefaults::seed($company);

            return $company;
        });

        $this->reset(['newNameAr', 'newNameEn', 'newCountry', 'newLicenseNo', 'founderCode']);
        \Flux\Flux::toast(text: __(':name created and handed to :owner.', ['name' => $company->name, 'owner' => $founder->name]), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('Umrah Companies')" :subtitle="__('Every licensed operator on the platform. What happens inside one is entirely its own business.')" />

    <x-ui.card :padded="false">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column minor>{{ __('Country') }}</flux:table.column>
                <flux:table.column minor>{{ __('Members') }}</flux:table.column>
                <flux:table.column>{{ __('Subscription') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->companies as $company)
                    <flux:table.row wire:key="company-{{ $company->id }}">
                        <flux:table.cell class="font-medium" :label="__('Name')">{{ $company->name }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Country')">{{ $company->country ?? '—' }}</flux:table.cell>
                        <flux:table.cell minor :label="__('Members')">{{ $company->members_count }}</flux:table.cell>
                        <flux:table.cell :label="__('Subscription')">
                            @if ($company->subscription_expires_at)
                                <flux:badge size="sm" :color="$company->isSubscriptionExpired() ? 'red' : 'zinc'">
                                    {{ $company->subscription_expires_at->format('Y-m-d') }}
                                </flux:badge>
                            @else
                                <span class="text-zinc-400">{{ __('No renewal date set') }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell :label="__('Status')">
                            <flux:badge size="sm" :color="$company->is_active ? 'lime' : 'zinc'">
                                {{ $company->is_active ? __('Active') : __('Deactivated') }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:link :href="route('admin.umrah-companies.show', $company)" wire:navigate>{{ __('Manage') }}</flux:link>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <x-ui.empty-state icon="building-office-2" :title="__('No Umrah companies yet.')" />
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </x-ui.card>

    <x-ui.card>
        <flux:heading size="lg">{{ __('New Umrah company') }}</flux:heading>
        <flux:subheading class="mb-4">
            {{ __('Ask the founding owner for their onboarding code first — entering it here both creates the company and hands it to them.') }}
        </flux:subheading>

        <form wire:submit="createCompany" class="flex flex-wrap items-end gap-4">
            <flux:input wire:model="newNameAr" :label="__('Name (Arabic)')" class="max-w-56" />
            <flux:input wire:model="newNameEn" :label="__('Name (English)')" class="max-w-56" />
            <flux:input wire:model="newCountry" :label="__('Country code')" placeholder="SA" class="max-w-32" />
            <flux:input wire:model="newLicenseNo" :label="__('License no.')" class="max-w-48" />
            <flux:input wire:model="founderCode" :label="__('Founding owner\'s onboarding code')" placeholder="K7P2QX9M" class="max-w-56" />
            <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
        </form>
    </x-ui.card>
</div>
