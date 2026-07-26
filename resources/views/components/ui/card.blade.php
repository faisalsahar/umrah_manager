@props(['padded' => true])

<div {{ $attributes->class([
    'rounded-[5px] border border-zinc-200/80 bg-white shadow-md shadow-zinc-950/[0.04] transition-shadow duration-200',
    'dark:border-white/10 dark:bg-zinc-900 dark:shadow-none',
    'p-6' => $padded,
]) }}>
    {{ $slot }}
</div>
