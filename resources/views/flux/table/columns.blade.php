@blaze(fold: true)

@props([
    'sticky' => false,
])

@php
    $classes = Flux::classes()
        ->add($sticky ? 'sticky top-0 z-20' : '')
        // Headers are replaced by each cell's own data-label below `sm` (see
        // table.cell), so hide the real header row on phones.
        ->add('max-sm:hidden')
    ;
@endphp

<thead {{ $attributes->class($classes) }} data-flux-columns>
    <tr {{ isset($tr) ? $tr->attributes : '' }}>
        {{ $tr ?? $slot }}
    </tr>
</thead>
