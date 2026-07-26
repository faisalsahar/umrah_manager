<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <div class="flex w-full flex-col items-center text-center">
            {{-- Kaaba-with-tawaf mark, in place of a text heading --}}
            <x-kaaba-tawaf class="mb-3" style="font-size: 6.25rem;" />
            <flux:subheading>{{ __('Enter your email and password below to log in') }}</flux:subheading>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                icon="envelope"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    icon="lock-closed"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <button
                type="submit"
                data-test="login-button"
                style="background: linear-gradient(to right, #38bdf8, #3b82f6); color: #ffffff; border-radius: 5px;"
                class="group relative inline-flex w-full items-center justify-center gap-2 overflow-hidden px-5 py-3 text-sm font-semibold shadow-lg shadow-blue-600/25 transition-all duration-200 hover:shadow-xl focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-500 focus-visible:ring-offset-2 focus-visible:ring-offset-white active:scale-[.99] dark:focus-visible:ring-offset-zinc-900"
            >
                <span>{{ __('Log in') }}</span>
                <svg class="size-4 transition-transform duration-200 group-hover:translate-x-0.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M3 10a.75.75 0 0 1 .75-.75h10.638L10.23 5.29a.75.75 0 1 1 1.04-1.08l5.5 5.25a.75.75 0 0 1 0 1.08l-5.5 5.25a.75.75 0 1 1-1.04-1.08l4.158-3.96H3.75A.75.75 0 0 1 3 10Z" clip-rule="evenodd" />
                </svg>
            </button>
        </form>

        <x-social-login>
            {{-- Passkey sign-in as the first round button in the provider row --}}
            <x-slot name="prepend">
                @assets
                    @vite('resources/js/passkeys.js')
                @endassets

                <div
                    x-data="{
                        supported: false,
                        loading: false,
                        updateSupport() { this.supported = Boolean(window.Passkeys?.isSupported()); },
                        init() {
                            this.updateSupport();
                            window.addEventListener('passkeys:ready', () => this.updateSupport(), { once: true });
                        },
                        async verify() {
                            this.loading = true;
                            try {
                                const response = await window.Passkeys.verify({
                                    routes: {
                                        options: '{{ route('passkey.login-options') }}',
                                        submit: '{{ route('passkey.login') }}',
                                    },
                                });
                                Livewire.navigate(response.redirect || '/dashboard');
                            } catch (e) {
                                if (e.constructor?.name !== 'UserCancelledError') { alert(e.message); }
                            } finally {
                                this.loading = false;
                            }
                        },
                    }"
                    x-show="supported"
                    x-cloak
                >
                    <button
                        type="button"
                        x-on:click="verify()"
                        x-bind:disabled="loading"
                        title="{{ __('Sign in with a passkey') }}"
                        aria-label="{{ __('Sign in with a passkey') }}"
                        class="flex size-11 items-center justify-center rounded-full border border-neutral-200 transition hover:bg-zinc-100 disabled:opacity-60 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        data-test="passkey-login"
                    >
                        <flux:icon.finger-print class="size-5 shrink-0 text-zinc-700 dark:text-zinc-300" />
                    </button>
                </div>
            </x-slot>
        </x-social-login>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" data-test="register-link" wire:navigate>
                {{ __('Sign up') }}
            </flux:link>
        </div>
    </div>
</x-layouts::auth>
