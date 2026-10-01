<?php

use App\Actions\Users\ChangeAccountType;
use App\Actions\Users\SuspendUser;
use App\Enums\UserRole;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Users')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: false)]
    public bool $suspendedOnly = false;

    public ?int $suspendingId = null;

    public string $suspensionReason = '';

    /**
     * Accounts matching the search and role filter, newest first.
     *
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->withCount(['bookings', 'vehicles'])
            ->when(filled($this->search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', '%'.trim($this->search).'%')
                ->orWhere('email', 'like', '%'.trim($this->search).'%')))
            ->when(UserRole::tryFrom($this->role), fn (Builder $query, UserRole $role) => $query->where('role', $role))
            ->when($this->suspendedOnly, fn (Builder $query) => $query->whereNotNull('suspended_at'))
            ->latest()
            ->paginate(15);
    }

    /**
     * Go back to the first page whenever the filters change.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'suspendedOnly'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Switch an account between renter and owner.
     */
    public function changeAccountType(int $userId, string $role, ChangeAccountType $changeAccountType): void
    {
        $user = User::findOrFail($userId);

        $changeAccountType->handle($user, UserRole::from($role), Auth::user());

        unset($this->users);

        Flux::toast(variant: 'success', text: __(':name is now a :role account.', ['name' => $user->name, 'role' => mb_strtolower($user->role->label())]));
    }

    /**
     * Open the suspension dialog for an account.
     */
    public function startSuspending(int $userId): void
    {
        $this->suspendingId = $userId;
        $this->suspensionReason = '';

        Flux::modal('suspend-user')->show();
    }

    /**
     * Suspend the account open in the dialog.
     */
    public function suspend(SuspendUser $suspendUser): void
    {
        $this->validate(['suspensionReason' => ['required', 'string', 'min:5', 'max:500']]);

        $user = User::findOrFail($this->suspendingId);
        $suspendUser->handle($user, Auth::user(), $this->suspensionReason);

        $this->reset(['suspendingId', 'suspensionReason']);
        unset($this->users);

        Flux::modal('suspend-user')->close();
        Flux::toast(variant: 'success', text: __(':name is suspended.', ['name' => $user->name]));
    }

    /**
     * Lift an account's suspension.
     */
    public function reinstate(int $userId, SuspendUser $suspendUser): void
    {
        $user = User::findOrFail($userId);
        $suspendUser->reinstate($user, Auth::user());

        unset($this->users);

        Flux::toast(variant: 'success', text: __(':name is reinstated.', ['name' => $user->name]));
    }
}; ?>

<div>
    <x-app.page-header :title="__('Users')" :description="__('Every account on CarHub. Fix an account type if someone signed up as the wrong kind of user.')" />

    <x-app.content class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-[1fr_14rem_auto] sm:items-center">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name or email')" clearable />
            <flux:select wire:model.live="role">
                <flux:select.option value="">{{ __('All account types') }}</flux:select.option>
                @foreach (UserRole::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:checkbox wire:model.live="suspendedOnly" :label="__('Suspended only')" />
        </div>

        <flux:error name="role" />

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white px-4 sm:px-6">
            <flux:table :paginate="$this->users">
                <flux:table.columns>
                    <flux:table.column>{{ __('User') }}</flux:table.column>
                    <flux:table.column>{{ __('Account type') }}</flux:table.column>
                    <flux:table.column>{{ __('Activity') }}</flux:table.column>
                    <flux:table.column>{{ __('Joined') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->users as $user)
                        <flux:table.row :key="$user->id">
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900">{{ $user->name }}</div>
                                <div class="text-xs text-zinc-500">{{ $user->email }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="match (true) { $user->isAdmin() => 'zinc', $user->isOwner() => 'green', default => 'blue' }">{{ $user->role->label() }}</flux:badge>
                                @if ($user->isOwner())
                                    <span class="ms-1 text-xs text-zinc-500">{{ $user->isVerifiedOwner() ? __('verified') : __('not verified') }}</span>
                                @endif
                                @if ($user->isSuspended())
                                    <div class="mt-1">
                                        <flux:badge size="sm" color="red">{{ __('Suspended') }}</flux:badge>
                                        <span class="text-xs text-zinc-500">{{ $user->suspension_reason }}</span>
                                    </div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-600">
                                @if ($user->isOwner())
                                    {{ trans_choice('{0} No vehicles|{1} :count vehicle|[2,*] :count vehicles', $user->vehicles_count, ['count' => $user->vehicles_count]) }}
                                @elseif ($user->isRenter())
                                    {{ trans_choice('{0} No bookings|{1} :count booking|[2,*] :count bookings', $user->bookings_count, ['count' => $user->bookings_count]) }}
                                @else
                                    &mdash;
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-600">{{ $user->created_at?->format('M j, Y') }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <div class="flex justify-end gap-2">
                                @if ($user->isRenter() && $user->bookings_count === 0)
                                    <flux:button size="sm" wire:click="changeAccountType({{ $user->id }}, 'owner')" wire:confirm="{{ __('Make :name an owner account? They will need owner verification before listing.', ['name' => $user->name]) }}">
                                        {{ __('Make owner') }}
                                    </flux:button>
                                @elseif ($user->isOwner() && $user->vehicles_count === 0)
                                    <flux:button size="sm" wire:click="changeAccountType({{ $user->id }}, 'renter')" wire:confirm="{{ __('Make :name a renter account?', ['name' => $user->name]) }}">
                                        {{ __('Make renter') }}
                                    </flux:button>
                                @endif
                                @unless ($user->isAdmin())
                                    @if ($user->isSuspended())
                                        <flux:button size="sm" wire:click="reinstate({{ $user->id }})">{{ __('Reinstate') }}</flux:button>
                                    @else
                                        <flux:button size="sm" variant="danger" wire:click="startSuspending({{ $user->id }})">{{ __('Suspend') }}</flux:button>
                                    @endif
                                @endunless
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </x-app.content>

    <flux:modal name="suspend-user" class="md:w-md">
        <form wire:submit="suspend" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Suspend this account?') }}</flux:heading>
                <flux:text class="mt-2">{{ __('They are signed out and cannot sign back in. An owner\'s listings leave the marketplace; existing bookings are not cancelled.') }}</flux:text>
            </div>
            <flux:textarea wire:model="suspensionReason" :label="__('Reason')" rows="3" />
            <flux:error name="suspension" />
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Suspend') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
