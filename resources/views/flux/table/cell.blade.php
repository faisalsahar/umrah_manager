@blaze(fold: true)

@props([
    'align' => 'start',
    'variant' => null,
    'sticky' => false,
    // Marks a cell as belonging to a "minor" column — see table.column. Must match
    // the corresponding column's `minor` prop.
    'minor' => false,
    // The column name shown as this cell's inline label on phones, where there's
    // no header row to read it from (see table.columns). Omit for a cell that
    // doesn't need one — e.g. a lone "Manage" action link.
    'label' => null,
])

@php
// A colspan cell is always an empty-state row — it should stay a single centered
// block on phones, not get the label:value split every ordinary cell gets.
$isSpanning = $attributes->has('colspan');

$classes = Flux::classes()
    // 5px padding — matches flux:table.column above (see its comment re: the
    // first/last column inset).
    ->add('[:where(&)]:py-[5px] [:where(&)]:px-[5px] first:ps-6 last:pe-6 text-sm')
    ->add($isSpanning
        ? 'max-sm:block'
        : 'max-sm:flex max-sm:items-center max-sm:justify-between max-sm:gap-3 max-sm:px-0 max-sm:py-1'
            .' max-sm:before:content-[attr(data-label)] max-sm:before:shrink-0 max-sm:before:text-xs max-sm:before:font-semibold'
            .' max-sm:before:uppercase max-sm:before:tracking-wide max-sm:before:text-zinc-500 max-sm:before:dark:text-zinc-400'
    )
    ->add($minor ? 'sm:hidden lg:table-cell' : '')
    ->add(match($align) {
        'center' => 'text-center [&>*]:mx-auto',
        'end' => 'text-end [&>*]:ms-auto',
        // Right is @deprecated but needed for backwards compatibility...
        'right' => 'text-end [&>*]:ms-auto',
        default => '',
    })
    ->add(match ($variant) {
        'strong' => 'font-medium text-zinc-800 dark:text-white',
        default => 'text-zinc-500 dark:text-zinc-300',
    })
    ->add($sticky ? [
        'z-10',
        'first:sticky first:left-0',
        'last:sticky last:right-0',
        'first:after:w-8 first:after:absolute first:after:inset-y-0 first:after:right-0 first:after:translate-x-full first:after:pointer-events-none',
        'last:after:w-8 last:after:absolute last:after:inset-y-0 last:after:left-0 last:after:-translate-x-full last:after:pointer-events-none',
        'in-data-scrolled-right:first:after:inset-shadow-[8px_0px_8px_-8px_rgba(0,0,0,0.05)]',
        'in-data-scrolled-left:last:after:inset-shadow-[-8px_0px_8px_-8px_rgba(0,0,0,0.05)]',
    ]: '')
    ->add('not-in-[tr:first-child]:border-t border-zinc-800/10 dark:border-white/20')
    ;
@endphp

<td {{ $attributes->class($classes) }} @if ($label && ! $isSpanning) data-label="{{ $label }}" @endif data-flux-cell>
    {{ $slot }}
</td>
