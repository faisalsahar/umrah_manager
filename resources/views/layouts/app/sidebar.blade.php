<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('azowar.locales.'.app()->getLocale().'.rtl', false) ? 'rtl' : 'ltr' }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-950">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200/80 bg-white dark:border-white/10 dark:bg-zinc-900">
            <flux:sidebar.header class="border-b border-zinc-200/80 pb-3 dark:border-white/10">
                <a href="{{ route('home') }}" class="flex h-16 flex-1 items-center justify-center" wire:navigate>
                    <x-kaaba-tawaf style="font-size: 3.5rem;" />
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @if (! auth()->user()->isAdmin() && $organization = auth()->user()->currentOrganization)
                @php
                    // Shrink the name to fit this narrow sidebar pill before falling
                    // back to truncation — a long organization name at the default
                    // size would just get clipped with no chance to read it in full.
                    $orgNameSizeClass = match (true) {
                        mb_strlen($organization->name) > 26 => 'text-[10px]',
                        mb_strlen($organization->name) > 18 => 'text-xs',
                        default => 'text-sm',
                    };
                @endphp
                <div class="in-data-flux-sidebar-collapsed-desktop:hidden mx-3 mt-3 flex items-center gap-2 rounded-[5px] bg-zinc-100/80 px-3 py-2 dark:bg-white/5">
                    <flux:icon.flag class="size-4 shrink-0 text-zinc-400" />
                    <div class="min-w-0 leading-tight">
                        <div class="truncate {{ $orgNameSizeClass }} font-medium text-zinc-700 dark:text-zinc-200">{{ $organization->name }}</div>
                        <div class="text-xs text-zinc-400">{{ $organization->type->label() }}</div>
                    </div>
                </div>
            @endif

            <flux:sidebar.nav class="mt-2">
                @if (auth()->user()->isAdmin())
                    {{-- The platform admin's whole world is the company roster — everything
                         inside a company is that company's own business, not the admin's. --}}
                    <flux:sidebar.group :heading="__('Admin')" class="grid">
                        <flux:sidebar.item icon="building-office-2" class="nav-icon-sky" :href="route('admin.umrah-companies.index')" :current="request()->routeIs('admin.umrah-companies.*')" wire:navigate>
                            {{ __('Umrah Companies') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @else
                    @php $currentOrgType = auth()->user()->currentOrganization?->type; @endphp
                    <flux:sidebar.group :heading="__('Platform')" class="grid">
                        <flux:sidebar.item icon="home" class="nav-icon-sky" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>
                        @if ($currentOrgType !== null)
                            <flux:sidebar.item icon="clipboard-document-list" class="nav-icon-violet" :href="route('operating-orders.index')" :current="request()->routeIs('operating-orders.*')" wire:navigate>
                                {{ __('Operating Orders') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="paper-airplane" class="nav-icon-orange" :href="route('movements.index')" :current="request()->routeIs('movements.*')" wire:navigate>
                                {{ __('Daily Movements') }}
                            </flux:sidebar.item>
                            @if ($currentOrgType === \App\Enums\OrganizationType::Agent)
                                <flux:sidebar.item icon="magnifying-glass" class="nav-icon-teal" :href="route('my-pilgrims.index')" :current="request()->routeIs('my-pilgrims.*')" wire:navigate>
                                    {{ __('My Pilgrims') }}
                                </flux:sidebar.item>
                            @else
                                <flux:sidebar.item icon="users" class="nav-icon-indigo" :href="route('groups.index')" :current="request()->routeIs('groups.*')" wire:navigate>
                                    {{ __('Groups & Pilgrims') }}
                                </flux:sidebar.item>
                            @endif
                        @endif
                    </flux:sidebar.group>

                    @if ($currentOrgType === \App\Enums\OrganizationType::Operator)
                    <flux:sidebar.group :heading="__('Supply')" class="grid">
                        <flux:sidebar.item icon="building-office-2" class="nav-icon-amber" :href="route('hotels.index')" :current="request()->routeIs('hotels.*')" wire:navigate>
                            {{ __('Hotels') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="truck" class="nav-icon-rose" :href="route('vehicles.index')" :current="request()->routeIs('vehicles.*')" wire:navigate>
                            {{ __('Vehicles') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="ticket" class="nav-icon-pink" :href="route('services.index')" :current="request()->routeIs('services.*')" wire:navigate>
                            {{ __('Services') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Catalog')" class="grid">
                        <flux:sidebar.item icon="map" class="nav-icon-emerald" :href="route('geography.index')" :current="request()->routeIs('geography.*')" wire:navigate>
                            {{ __('Geography') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="arrows-right-left" class="nav-icon-cyan" :href="route('movement-types.index')" :current="request()->routeIs('movement-types.*')" wire:navigate>
                            {{ __('Movement Types') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="identification" class="nav-icon-fuchsia" :href="route('permit-types.index')" :current="request()->routeIs('permit-types.*')" wire:navigate>
                            {{ __('Permit Types') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                    @endif

                    @if ($currentOrgType !== null)
                        <flux:sidebar.group :heading="__('Account')" class="grid">
                            <flux:sidebar.item icon="briefcase" class="nav-icon-lime" :href="route('organizations.index')" :current="request()->routeIs('organizations.*')" wire:navigate>
                                {{ __('Organizations') }}
                            </flux:sidebar.item>
                        </flux:sidebar.group>
                    @endif
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="border-b border-zinc-200/80 lg:hidden dark:border-white/10">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <a href="{{ route('home') }}" class="flex items-center" wire:navigate>
                <x-kaaba-tawaf style="font-size: 2.5rem;" />
            </a>

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
