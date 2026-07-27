<?php

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Organizations')] class extends Component {
    public string $newMemberCode = '';

    public string $newNameAr = '';

    public string $newNameEn = '';

    public string $newType = '';

    public string $newCountry = '';

    public string $newParentOrganizationId = '';

    public string $memberCode = '';

    public string $memberRole = '';

    /**
     * Candidate parent agents for a new sub-agent — only meaningful when the new
     * organization being created is itself an agent.
     */
    public function getParentAgentsProperty()
    {
        return Organization::ofType(OrganizationType::Agent)->orderBy('name_en')->get();
    }

    /**
     * Every organization type a manager may bring someone in as. Umrah companies
     * (Operator) are excluded — only the platform admin creates those, see
     * pages::admin.umrah-companies.index.
     *
     * @return list<OrganizationType>
     */
    public function getCreatableTypesProperty(): array
    {
        return array_values(array_filter(
            OrganizationType::cases(),
            fn (OrganizationType $type): bool => $type !== OrganizationType::Operator,
        ));
    }

    /**
     * The roles that make sense to assign inside the current organization — see
     * OrganizationRole::availableFor().
     *
     * @return list<OrganizationRole>
     */
    public function getMemberRolesProperty(): array
    {
        $current = Auth::user()->currentOrganization;

        return $current ? OrganizationRole::availableFor($current->type) : [];
    }

    /**
     * Every organization the current user belongs to, with their role.
     */
    public function getMembershipsProperty()
    {
        return Auth::user()->organizations()->orderBy('name_en')->get();
    }

    /**
     * Whether the current user manages (owns/admins) their current organization —
     * gates the "add member" and "create organization" panels.
     */
    public function getCanManageProperty(): bool
    {
        $current = Auth::user()->currentOrganization;

        return $current && Auth::user()->roleIn($current)?->managesOrganization();
    }

    /**
     * Switch which organization the user is acting as. Every other screen scopes to
     * whatever this is set to.
     */
    public function switchTo(int $organizationId): void
    {
        $user = Auth::user();

        abort_unless($user->belongsToOrganization(Organization::findOrFail($organizationId)), 403);

        $user->forceFill(['current_organization_id' => $organizationId])->save();

        $this->redirect(route('organizations.index'), navigate: true);
    }

    /**
     * Create a new organization for someone else, by their onboarding code, and
     * make *them* its owner — the only way a non-Umrah-company organization comes
     * into existence. There is no self-service path: a user can't spin up their own
     * Agent/Hotel/Transport Supplier organization just by asking for one — an
     * existing organization's manager has to vouch for them by pulling in their
     * code and choosing what they become. Umrah companies themselves are still
     * only ever created by the platform admin (see pages::admin.umrah-companies).
     */
    public function bringInOrganization(): void
    {
        abort_unless($this->canManage, 403);

        $validated = $this->validate([
            'newMemberCode' => ['required', 'string'],
            'newNameAr' => ['required', 'string', 'max:255'],
            'newNameEn' => ['required', 'string', 'max:255'],
            // Umrah companies (Operator) are excluded here — only the admin panel
            // creates those, so even a tampered request can't self-provision one.
            'newType' => ['required', Rule::enum(OrganizationType::class)->except(OrganizationType::Operator)],
            'newCountry' => ['nullable', 'string', 'size:2'],
            // Only an agent can have a parent agent (a sub-agent) — otherwise a
            // tampered request could nest, say, an operator under an agent.
            'newParentOrganizationId' => [
                'nullable', 'integer',
                Rule::exists('organizations', 'id')->where('type', OrganizationType::Agent->value),
                Rule::excludeIf($this->newType !== OrganizationType::Agent->value),
            ],
        ]);

        $owner = User::where('onboarding_code', strtoupper($validated['newMemberCode']))->first();

        if (! $owner) {
            $this->addError('newMemberCode', __('No user was found with that onboarding code.'));

            return;
        }

        $organization = Organization::create([
            'type' => $validated['newType'],
            'name_ar' => $validated['newNameAr'],
            'name_en' => $validated['newNameEn'],
            'country' => $validated['newCountry'] ?: null,
            'parent_organization_id' => $validated['newType'] === OrganizationType::Agent->value
                ? ($validated['newParentOrganizationId'] ?: null)
                : null,
        ]);

        $organization->addMember($owner, OrganizationRole::Owner);

        $this->reset(['newMemberCode', 'newNameAr', 'newNameEn', 'newType', 'newCountry', 'newParentOrganizationId']);
        \Flux\Flux::toast(text: __(':name created and handed to :owner.', ['name' => $organization->name, 'owner' => $owner->name]), variant: 'success');
    }

    /**
     * Add an existing user to the current organization by their onboarding code —
     * the identifier a person shares instead of an email address (see
     * User::generateOnboardingCode()).
     */
    public function addMemberByCode(): void
    {
        $current = Auth::user()->currentOrganization;
        abort_unless($current && Auth::user()->roleIn($current)?->managesOrganization(), 403);

        $validated = $this->validate([
            'memberCode' => ['required', 'string'],
            // Scoped to the roles that make sense in this organization's own type —
            // e.g. an Operator can't be given an AgentUser role.
            'memberRole' => ['required', Rule::in(array_map(fn (OrganizationRole $role): string => $role->value, OrganizationRole::availableFor($current->type)))],
        ]);

        $newMember = User::where('onboarding_code', strtoupper($validated['memberCode']))->first();

        if (! $newMember) {
            $this->addError('memberCode', __('No user was found with that onboarding code.'));

            return;
        }

        $current->addMember($newMember, OrganizationRole::from($validated['memberRole']));

        $this->reset(['memberCode', 'memberRole']);
        \Flux\Flux::toast(text: __(':name added to :organization.', ['name' => $newMember->name, 'organization' => $current->name]), variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-ui.page-header :title="__('Organizations')" :subtitle="__('Every party you belong to, and the role you hold there.')" />

    <x-ui.card :padded="false">
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Organization') }}</flux:table.column>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column minor>{{ __('Your role') }}</flux:table.column>
                <flux:table.column>{{ __('Acting as') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($this->memberships as $organization)
                    <flux:table.row wire:key="org-{{ $organization->id }}">
                        <flux:table.cell class="font-medium" :label="__('Organization')">{{ $organization->name }}</flux:table.cell>
                        <flux:table.cell :label="__('Type')"><flux:badge size="sm">{{ $organization->type->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell minor :label="__('Your role')">{{ \App\Enums\OrganizationRole::from($organization->pivot->role)->label() }}</flux:table.cell>
                        <flux:table.cell :label="auth()->user()->current_organization_id === $organization->id ? __('Acting as') : null">
                            @if (auth()->user()->current_organization_id === $organization->id)
                                <flux:badge color="lime" size="sm">{{ __('Current') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if (auth()->user()->current_organization_id !== $organization->id)
                                <flux:button size="sm" wire:click="switchTo({{ $organization->id }})">
                                    {{ __('Switch to') }}
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </x-ui.card>

    @if ($this->canManage)
        <x-ui.card>
            <flux:heading size="lg">{{ __('Add a member') }}</flux:heading>
            <flux:subheading class="mb-4">
                {{ __('Add someone to :organization by their onboarding code — no email address needed.', ['organization' => auth()->user()->currentOrganization->name]) }}
            </flux:subheading>

            <form wire:submit="addMemberByCode" class="flex flex-wrap items-end gap-4">
                <flux:input wire:model="memberCode" :label="__('Onboarding code')" placeholder="K7P2QX9M" class="max-w-48" />
                <flux:select wire:model="memberRole" :label="__('Role')" class="max-w-48">
                    <flux:select.option value="">{{ __('Choose a role') }}</flux:select.option>
                    @foreach ($this->memberRoles as $role)
                        <flux:select.option value="{{ $role->value }}">{{ $role->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit" variant="primary">{{ __('Add') }}</flux:button>
            </form>
        </x-ui.card>
    @endif

    @if ($this->canManage)
        <x-ui.card>
            <flux:heading size="lg">{{ __('Bring in an organization') }}</flux:heading>
            <flux:subheading class="mb-4">
                {{ __('Someone you work with — an agent, hotel, or transport supplier — gives you their onboarding code. Creating it here makes them its owner, not you. There is no other way to start one; only the platform admin creates Umrah companies.') }}
            </flux:subheading>

            <form wire:submit="bringInOrganization" class="flex flex-wrap items-end gap-4">
                <flux:input wire:model="newMemberCode" :label="__('Their onboarding code')" placeholder="K7P2QX9M" class="max-w-48" />
                <flux:input wire:model="newNameAr" :label="__('Name (Arabic)')" class="max-w-56" />
                <flux:input wire:model="newNameEn" :label="__('Name (English)')" class="max-w-56" />
                <flux:select wire:model.live="newType" :label="__('Type')" class="max-w-48">
                    <flux:select.option value="">{{ __('Choose a type') }}</flux:select.option>
                    @foreach ($this->creatableTypes as $type)
                        <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="newCountry" :label="__('Country code')" placeholder="SA" class="max-w-32" />
                @if ($newType === \App\Enums\OrganizationType::Agent->value)
                    <flux:select wire:model="newParentOrganizationId" :label="__('Sub-agent of')" class="max-w-56">
                        <flux:select.option value="">{{ __('— Not a sub-agent —') }}</flux:select.option>
                        @foreach ($this->parentAgents as $parentAgent)
                            <flux:select.option value="{{ $parentAgent->id }}">{{ $parentAgent->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
                <flux:button type="submit" variant="primary">{{ __('Create') }}</flux:button>
            </form>
        </x-ui.card>
    @endif
</div>
