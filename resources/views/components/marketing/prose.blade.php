{{-- Hand-rolled typographic scope for long-form legal copy. @tailwindcss/typography is
     not a dependency of this project, so the styles are applied with child selectors. --}}
<div {{ $attributes->class([
    'text-base/7 text-zinc-600',
    '[&_h2]:mt-12 [&_h2]:scroll-mt-24 [&_h2]:text-xl [&_h2]:font-bold [&_h2]:tracking-tight [&_h2]:text-zinc-900 first:[&_h2]:mt-0',
    '[&_h3]:mt-8 [&_h3]:text-base [&_h3]:font-semibold [&_h3]:text-zinc-900',
    '[&_p]:mt-4',
    '[&_ul]:mt-4 [&_ul]:space-y-2 [&_ul]:ps-1',
    '[&_li]:relative [&_li]:ps-6',
    '[&_li]:before:absolute [&_li]:before:start-0 [&_li]:before:top-[0.6875rem] [&_li]:before:size-1.5 [&_li]:before:rounded-full [&_li]:before:bg-brand-400',
    '[&_strong]:font-semibold [&_strong]:text-zinc-900',
    '[&_a]:font-medium [&_a]:text-brand-600 [&_a]:underline [&_a]:underline-offset-2 hover:[&_a]:text-brand-700',
]) }}>
    {{ $slot }}
</div>
