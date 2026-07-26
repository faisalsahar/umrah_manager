<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Language settings')] class extends Component {
    public string $locale = '';

    public function mount(): void
    {
        $this->locale = Auth::user()->locale ?? config('app.locale');
    }

    public function updatedLocale(string $value): void
    {
        if (! array_key_exists($value, config('azowar.locales', []))) {
            return;
        }

        Auth::user()->forceFill(['locale' => $value])->save();

        // Remembered even after logout — see SetLocale middleware and
        // AppServiceProvider::rememberLocaleCookieOnLogin() for why the
        // guest-facing login screen also reads this cookie.
        Cookie::queue(Cookie::forever('locale', $value));

        // A full reload (not a wire:navigate SPA hop) so <html lang/dir> and every
        // server-rendered string on the page reflect the new language immediately.
        $this->redirect(route('language.edit'), navigate: false);
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Language settings') }}</flux:heading>

    <x-pages::settings.layout :heading="__('Language')" :subheading="__('Choose the language used throughout the app')">
        <flux:radio.group wire:model.live="locale" variant="segmented">
            @foreach (config('azowar.locales', []) as $code => $info)
                <flux:radio value="{{ $code }}">{{ $info['flag'] }} {{ $info['native'] }}</flux:radio>
            @endforeach
        </flux:radio.group>
    </x-pages::settings.layout>
</section>
