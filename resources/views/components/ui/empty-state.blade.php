@props([
    'icon' => 'inbox',
    'title',
])

<div class="flex flex-col items-center gap-3 py-12 text-center">
    <div class="flex size-14 items-center justify-center rounded-full bg-zinc-100 text-zinc-400 ring-1 ring-zinc-200/60 dark:bg-white/5 dark:text-zinc-500 dark:ring-white/10">
        <flux:icon :icon="$icon" class="size-6" />
    </div>
    <flux:text class="text-zinc-500 dark:text-zinc-400">{{ $title }}</flux:text>
    @isset($action)
        <div class="mt-1">{{ $action }}</div>
    @endisset
</div>
