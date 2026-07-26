@blaze(fold: true)

@props([
    'align' => 'start',
    'sortable' => false,
    'sorted' => false,
    'direction' => null,
    'sticky' => false,
    // Marks a column as OK to drop on a tablet-width screen (640-1024px) to keep
    // the table from feeling cramped — it reappears at the desktop breakpoint.
    // Never hidden on phones, since the card layout there has room for everything.
    'minor' => false,
])

@php
$classes = Flux::classes()
    // A bit more vertical breathing room than the 5px body cells (flux:table.cell)
    // — that extra weight plus the shaded band below is what makes the header
    // read as its own row instead of just another line of the table.
    ->add('py-2.5 px-[5px] first:ps-6 last:pe-6')
    // A shaded band makes the header row read as its own distinct region rather
    // than blending into the body — the same identical background on every <th>
    // merges into one continuous band since cells sit flush (border-separate,
    // spacing 0). Rounded on the outer corners for tables with no heading above
    // them, where the header is the card's first visible element.
    ->add('bg-zinc-50 first:rounded-ss-[5px] last:rounded-se-[5px] dark:bg-white/[0.04]')
    ->add('text-start text-xs font-bold uppercase tracking-wide text-zinc-700 dark:text-zinc-200')
    // 2px, sky-600 at 50% — the same blue as the circulating digits in the Kaaba
    // tawaf logo.
    ->add('border-b-2 border-sky-600/50 dark:border-sky-500/50')
    ->add($minor ? 'sm:hidden lg:table-cell' : '')
    ->add(match($align) {
        'center' => 'group/center-align',
        'end' => 'group/end-align',
        // Right is @deprecated but needed for backwards compatibility...
        'right' => 'group/end-align',
        default => '',
    })
    ->add($sortable ? 'group/sortable' : '')
    ->add($sticky ? [
        'z-10',
        'first:sticky first:left-0',
        'last:sticky last:right-0',
        'first:after:w-8 first:after:absolute first:after:inset-y-0 first:after:right-0 first:after:translate-x-full first:after:pointer-events-none',
        'last:after:w-8 last:after:absolute last:after:inset-y-0 last:after:left-0 last:after:-translate-x-full last:after:pointer-events-none',
        'in-data-scrolled-right:first:after:inset-shadow-[8px_0px_8px_-8px_rgba(0,0,0,0.05)]',
        'in-data-scrolled-left:last:after:inset-shadow-[-8px_0px_8px_-8px_rgba(0,0,0,0.05)]',
    ]: '')
    // If the last column is sortable, remove the right negative margin that the sortable applies to itself, as the
    // negative margin caused the last column to overflow the table creating an unnecessary horizontal scrollbar...
    ->add('**:data-flux-table-sortable:last:me-0')
    ;
@endphp

<th {{ $attributes->class($classes) }} data-flux-column>
    <?php if ($sortable): ?>
        <div class="flex in-[.group\/center-align]:justify-center in-[.group\/end-align]:justify-end">
            <flux:table.sortable :$sorted :direction="$direction">
                <div>{{ $slot }}</div>
            </flux:table.sortable>
        </div>
    <?php else: ?>
        <div class="flex in-[.group\/center-align]:justify-center in-[.group\/end-align]:justify-end">{{ $slot }}</div>
    <?php endif; ?>
</th>
