{{-- Compact typographic scope for rental terms and contracts inside the app shell.
     Like x-marketing.prose, it styles child elements directly, with dark-mode variants. --}}
<div {{ $attributes->class([
    'text-sm/6 text-zinc-600 dark:text-zinc-300',
    '[&_h2]:mt-6 [&_h2]:text-base [&_h2]:font-semibold [&_h2]:text-zinc-900 first:[&_h2]:mt-0 dark:[&_h2]:text-white',
    '[&_h3]:mt-4 [&_h3]:font-semibold [&_h3]:text-zinc-900 dark:[&_h3]:text-white',
    '[&_p]:mt-2',
    '[&_ul]:mt-2 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:ps-5',
    '[&_strong]:font-semibold [&_strong]:text-zinc-900 dark:[&_strong]:text-white',
]) }}>
    {{ $slot }}
</div>
