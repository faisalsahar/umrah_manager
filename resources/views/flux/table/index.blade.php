@blaze(fold: true)

@props([
    'paginate' => null,
])

@php
$classes = Flux::classes()
    ->add('[:where(&)]:min-w-full table-fixed border-separate border-spacing-0 isolate')
    ->add('text-zinc-800')
    // Below `sm` the table stops behaving like a table at all — see table.row,
    // which turns each row into its own stacked card — so exit table layout mode.
    ->add('max-sm:block max-sm:w-full')
    ->add('whitespace-normal sm:whitespace-nowrap [&_dialog]:whitespace-normal [&_[popover]]:whitespace-normal')
    ;

$containerClasses = Flux::classes()
    ->add('flex flex-col')
    ->add($attributes->pluck('container:class'))
    ;
@endphp

<div class="{{ $containerClasses }}">
    {{ $header ?? '' }}

    <ui-table-scroll-area class="overflow-auto">
        <table {{ $attributes->class($classes) }} data-flux-table>
            {{ $slot }}
        </table>
    </ui-table-scroll-area>

    {{ $footer ?? '' }}

    <?php if ($paginate): ?>
        <?php $paginationAttributes = Flux::attributesAfter('pagination:', $attributes, ['paginator' => $paginate, 'class' => 'shrink-0 mx-6 mb-6']); ?>
        <flux:pagination :attributes="$paginationAttributes" />
    <?php endif; ?>
</div>
