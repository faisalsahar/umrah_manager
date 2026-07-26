@props([
    'label',
    'value',
    'icon' => null,
    'color' => 'sky',
])

@php
    $tints = [
        'sky' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400',
        'lime' => 'bg-lime-50 text-lime-600 dark:bg-lime-500/10 dark:text-lime-400',
        'amber' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
        'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400',
        'zinc' => 'bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300',
    ];
    $tint = $tints[$color] ?? $tints['sky'];
@endphp

<x-ui.card {{ $attributes->class('flex items-center gap-4') }}>
    @if ($icon)
        <div class="flex size-11 shrink-0 items-center justify-center rounded-[5px] {{ $tint }}">
            <flux:icon :icon="$icon" class="size-5" />
        </div>
    @endif
    <div class="min-w-0">
        <div class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ $label }}</div>
        <div class="text-2xl font-semibold tracking-tight text-zinc-900 dark:text-white">{{ $value }}</div>
    </div>
</x-ui.card>
