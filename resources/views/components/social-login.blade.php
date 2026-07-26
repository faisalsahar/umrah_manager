@php
    // Display order; only providers with credentials configured are shown.
    $providers = [
        'google' => 'Google',
        'github' => 'GitHub',
        'microsoft' => 'Microsoft',
        'apple' => 'Apple',
        'linkedin-openid' => 'LinkedIn',
        'facebook' => 'Facebook',
    ];

    $enabled = array_filter(
        $providers,
        fn (string $label, string $key): bool => filled(config("services.{$key}.client_id")),
        ARRAY_FILTER_USE_BOTH,
    );
@endphp

{{-- The row still renders with no OAuth providers configured, so the prepended
     passkey button remains reachable on a fresh install. --}}
@if ($enabled !== [] || isset($prepend))
    <div class="flex flex-col gap-4">
        <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></div>
            <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('or continue with') }}</span>
            <div class="h-px flex-1 bg-zinc-200 dark:bg-zinc-700"></div>
        </div>

        <div class="flex flex-wrap items-center justify-center gap-3">
            {{ $prepend ?? '' }}

            @foreach ($enabled as $key => $label)
                <a
                    href="{{ route('social.redirect', $key) }}"
                    title="{{ __('Continue with :provider', ['provider' => $label]) }}"
                    aria-label="{{ __('Continue with :provider', ['provider' => $label]) }}"
                    class="flex size-11 items-center justify-center rounded-full border border-neutral-200 transition hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
                    data-test="social-login-{{ $key }}"
                >
                    <x-social-icon :provider="$key" class="size-5 shrink-0" />
                </a>
            @endforeach
        </div>
    </div>
@endif
