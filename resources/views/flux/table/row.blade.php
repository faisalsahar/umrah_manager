@blaze(fold: true, safe: ['key'])

@props([
    'key' => null,
    'sticky' => false,
])

@php
$classes = Flux::classes()
    // A faint zebra tint on even rows helps the eye track across a wide table;
    // hover is deliberately a full step stronger so it stays obvious over the tint.
    ->add('transition-colors even:bg-zinc-50/60 hover:bg-zinc-100 dark:even:bg-white/[0.02] dark:hover:bg-white/[0.06]')
    // Below `sm` each row is its own card, not a table row — see table.cell for
    // the label:value layout inside it. The zebra tint above only applies at
    // table-layout sizes, so cards stay plain white there.
    ->add('max-sm:block max-sm:rounded-[5px] max-sm:border max-sm:border-zinc-200/80 max-sm:bg-white max-sm:p-3 max-sm:even:bg-white max-sm:dark:border-white/10 max-sm:dark:bg-zinc-900 max-sm:dark:even:bg-zinc-900')
    ->add($sticky ? 'last:sticky last:bottom-0 last:z-20' : '')
    ;
@endphp

<tr @if ($key) wire:key="table-{{ $key }}" @endif {{ $attributes->class($classes) }} data-flux-row>
    {{ $slot }}
</tr>
