@props([
    'title' => null,
    'description' => null,
])

<div class="flex w-full flex-col items-center text-center">
    @if (filled($title))
        <flux:heading size="xl" class="tracking-tight">{{ $title }}</flux:heading>
    @endif
    @if (filled($description))
        <flux:subheading>{{ $description }}</flux:subheading>
    @endif
</div>
