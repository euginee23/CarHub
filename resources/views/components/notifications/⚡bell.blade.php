<?php

use App\Support\NotificationPresenter;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    /**
     * The user's most recent notifications.
     *
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function notifications(): Collection
    {
        return Auth::user()->notifications()->latest()->take(8)->get();
    }

    /**
     * How each notification shows: icon, colour, status chip, action, and destination.
     *
     * @return array<string, array{icon: string, tone: string, chip: string|null, action: string, url: string}>
     */
    #[Computed]
    public function presented(): array
    {
        return NotificationPresenter::presentAll($this->notifications, Auth::user());
    }

    /**
     * How many notifications the user has not read yet.
     */
    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    /**
     * Mark a notification as read and go to where it needs the user now.
     */
    public function open(string $notificationId): void
    {
        $notification = Auth::user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        $this->redirect(NotificationPresenter::presentAll(collect([$notification]), Auth::user())[$notification->id]['url']);
    }

    /**
     * Mark every notification as read.
     */
    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        unset($this->notifications, $this->presented, $this->unreadCount);
    }
}; ?>

{{-- The panel flag is not called "open": that would shadow the open() action in wire:click. --}}
<div x-data="{ panelOpen: false }" x-on:keydown.escape.window="panelOpen = false" class="relative" wire:poll.visible.60s>
    <button
        type="button"
        x-on:click="panelOpen = ! panelOpen"
        x-bind:aria-expanded="panelOpen ? 'true' : 'false'"
        class="relative flex size-10 items-center justify-center rounded-lg text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900"
        data-test="notifications-button"
    >
        <svg @class(['size-5', 'origin-top motion-safe:animate-wave' => $this->unreadCount > 0]) viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        @if ($this->unreadCount > 0)
            <span class="absolute end-1 top-1 flex min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-4 text-white">
                {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
            </span>
        @endif
        <span class="sr-only">{{ trans_choice('{0} Notifications|{1} :count unread notification|[2,*] :count unread notifications', $this->unreadCount, ['count' => $this->unreadCount]) }}</span>
    </button>

    <div
        x-show="panelOpen"
        x-cloak
        x-transition.origin.top.right
        x-on:click.outside="panelOpen = false"
        class="fixed inset-x-4 top-16 z-50 overflow-hidden sm:absolute sm:inset-x-auto sm:end-0 sm:top-auto sm:mt-2 sm:w-96 rounded-2xl border border-zinc-200 bg-white shadow-xl shadow-zinc-900/10"
    >
        <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-4 py-3">
            <div>
                <p class="text-sm font-semibold text-zinc-900">{{ __('Notifications') }}</p>
                @if ($this->notifications->isNotEmpty())
                    <p class="text-xs text-zinc-500">{{ __('Tap one to go straight there.') }}</p>
                @endif
            </div>
            @if ($this->unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="shrink-0 text-xs font-semibold text-brand-600 hover:text-brand-700">{{ __('Mark all as read') }}</button>
            @endif
        </div>

        <div class="max-h-[65vh] divide-y divide-zinc-100 overflow-y-auto">
            @forelse ($this->notifications as $notification)
                @php
                    $shown = $this->presented[$notification->id];
                    $unread = $notification->read_at === null;
                    $tone = [
                        'brand' => ['icon' => 'bg-brand-100 text-brand-700', 'chip' => 'bg-brand-50 text-brand-700 ring-brand-200', 'action' => 'bg-brand-600 text-white group-hover:bg-brand-700'],
                        'amber' => ['icon' => 'bg-amber-100 text-amber-700', 'chip' => 'bg-amber-50 text-amber-800 ring-amber-200', 'action' => 'bg-amber-500 text-white group-hover:bg-amber-600'],
                        'emerald' => ['icon' => 'bg-emerald-100 text-emerald-700', 'chip' => 'bg-emerald-50 text-emerald-700 ring-emerald-200', 'action' => 'bg-emerald-600 text-white group-hover:bg-emerald-700'],
                        'red' => ['icon' => 'bg-red-100 text-red-700', 'chip' => 'bg-red-50 text-red-700 ring-red-200', 'action' => 'bg-red-600 text-white group-hover:bg-red-700'],
                        'zinc' => ['icon' => 'bg-zinc-100 text-zinc-500', 'chip' => 'bg-zinc-50 text-zinc-600 ring-zinc-200', 'action' => 'bg-zinc-100 text-zinc-700 group-hover:bg-zinc-200'],
                    ][$shown['tone']] ?? ['icon' => 'bg-brand-100 text-brand-700', 'chip' => 'bg-brand-50 text-brand-700 ring-brand-200', 'action' => 'bg-brand-600 text-white group-hover:bg-brand-700'];
                @endphp

                <button
                    type="button"
                    wire:key="notification-{{ $notification->id }}"
                    wire:click="open('{{ $notification->id }}')"
                    @class([
                        'group flex w-full items-start gap-3 px-4 py-3.5 text-start transition hover:bg-zinc-50 data-loading:opacity-60',
                        'bg-brand-50/50' => $unread,
                    ])
                    data-test="notification"
                >
                    <span @class(['relative mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-full', $tone['icon']])>
                        <flux:icon :name="$shown['icon']" variant="mini" class="size-5" />
                        @if ($unread)
                            <span class="absolute -end-0.5 -top-0.5 size-3 rounded-full bg-brand-600 ring-2 ring-white" aria-hidden="true"></span>
                        @endif
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="flex items-start justify-between gap-2">
                            <span @class(['text-sm text-zinc-900', 'font-bold' => $unread, 'font-semibold' => ! $unread])>{{ $notification->data['title'] ?? __('Update') }}</span>
                            <span class="mt-0.5 shrink-0 text-xs text-zinc-400">{{ $notification->created_at->diffForHumans(short: true) }}</span>
                        </span>
                        <span class="mt-0.5 block text-sm leading-snug text-zinc-600">{{ $notification->data['body'] ?? '' }}</span>

                        <span class="mt-2.5 flex flex-wrap items-center gap-2">
                            <span @class(['inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition', $tone['action']])>
                                {{ $shown['action'] }}
                                <flux:icon.arrow-right variant="micro" class="size-3.5 transition group-hover:translate-x-0.5" />
                            </span>
                            @if ($shown['chip'])
                                <span @class(['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.7rem] font-semibold ring-1', $tone['chip']])>
                                    @if ($shown['chip'] === __('Needs your answer') || $shown['chip'] === __('Action needed'))
                                        <span class="size-1.5 rounded-full bg-current motion-safe:animate-pulse" aria-hidden="true"></span>
                                    @endif
                                    {{ $shown['chip'] }}
                                </span>
                            @endif
                        </span>
                    </span>
                </button>
            @empty
                <div class="px-4 py-10 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <flux:icon.check variant="mini" class="size-6" />
                    </span>
                    <p class="mt-3 text-sm font-semibold text-zinc-900">{{ __('You are all caught up.') }}</p>
                    <p class="mt-1 text-xs text-zinc-500">{{ __('We’ll let you know here when something needs you.') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
