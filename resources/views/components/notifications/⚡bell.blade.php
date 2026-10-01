<?php

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
     * How many notifications the user has not read yet.
     */
    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    /**
     * Mark a notification as read and go to what it is about.
     */
    public function open(string $notificationId): void
    {
        $notification = Auth::user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        $this->redirect($notification->data['url'] ?? route('dashboard'));
    }

    /**
     * Mark every notification as read.
     */
    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        unset($this->notifications, $this->unreadCount);
    }
}; ?>

<div x-data="{ open: false }" x-on:keydown.escape.window="open = false" class="relative" wire:poll.visible.60s>
    <button
        type="button"
        x-on:click="open = ! open"
        x-bind:aria-expanded="open ? 'true' : 'false'"
        class="relative flex size-10 items-center justify-center rounded-lg text-zinc-600 transition-colors hover:bg-zinc-100 hover:text-zinc-900"
        data-test="notifications-button"
    >
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
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
        x-show="open"
        x-cloak
        x-transition.origin.top.right
        x-on:click.outside="open = false"
        class="absolute end-0 z-50 mt-2 w-80 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-xl shadow-zinc-900/10 sm:w-96"
    >
        <div class="flex items-center justify-between gap-3 border-b border-zinc-100 px-4 py-3">
            <p class="text-sm font-semibold text-zinc-900">{{ __('Notifications') }}</p>
            @if ($this->unreadCount > 0)
                <button type="button" wire:click="markAllAsRead" class="text-xs font-semibold text-brand-600 hover:text-brand-700">{{ __('Mark all as read') }}</button>
            @endif
        </div>

        <div class="max-h-[60vh] divide-y divide-zinc-100 overflow-y-auto">
            @forelse ($this->notifications as $notification)
                <button
                    type="button"
                    wire:key="notification-{{ $notification->id }}"
                    wire:click="open('{{ $notification->id }}')"
                    @class([
                        'block w-full px-4 py-3 text-start transition hover:bg-zinc-50',
                        'bg-brand-50/60' => $notification->read_at === null,
                    ])
                >
                    <p class="flex items-center gap-2 text-sm font-semibold text-zinc-900">
                        @if ($notification->read_at === null)
                            <span class="size-2 shrink-0 rounded-full bg-brand-600" aria-hidden="true"></span>
                        @endif
                        {{ $notification->data['title'] ?? __('Update') }}
                    </p>
                    <p class="mt-0.5 line-clamp-2 text-sm text-zinc-600">{{ $notification->data['body'] ?? '' }}</p>
                    <p class="mt-1 text-xs text-zinc-400">{{ $notification->created_at->diffForHumans() }}</p>
                </button>
            @empty
                <p class="px-4 py-8 text-center text-sm text-zinc-500">{{ __('You are all caught up.') }}</p>
            @endforelse
        </div>
    </div>
</div>
