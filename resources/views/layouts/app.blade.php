{{-- Signed-in pages share the public site's shell — the same top navigation,
     footer, and light brand — so the whole product reads as one. Each page opens
     with <x-app.page-header> and puts its body in <x-app.content>. --}}
<x-layouts::marketing :title="$title ?? null" :noindex="true">
    <div class="min-h-[60vh] bg-zinc-50">
        {{ $slot }}
    </div>
</x-layouts::marketing>
