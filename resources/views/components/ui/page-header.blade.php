@props([
    'title',
    'subtitle' => null,
])

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <flux:heading size="xl">{{ $title }}</flux:heading>
        @if ($subtitle)
            <flux:subheading class="mt-1 max-w-2xl">{{ $subtitle }}</flux:subheading>
        @endif
    </div>

    @isset($actions)
        <div class="flex items-center gap-3">{{ $actions }}</div>
    @endisset
</div>
