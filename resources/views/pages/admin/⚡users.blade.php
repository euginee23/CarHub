<?php

use App\Actions\Users\ChangeAccountType;
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
            ->latest()
            ->paginate(15);
    }

    /**
     * Go back to the first page whenever the filters change.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role'], true)) {
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
}; ?>

<div>
    <x-app.page-header :title="__('Users')" :description="__('Every account on CarHub. Fix an account type if someone signed up as the wrong kind of user.')" />

    <x-app.content class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-[1fr_14rem]">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search by name or email')" clearable />
            <flux:select wire:model.live="role">
                <flux:select.option value="">{{ __('All account types') }}</flux:select.option>
                @foreach (UserRole::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
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
                                @if ($user->isRenter() && $user->bookings_count === 0)
                                    <flux:button size="sm" wire:click="changeAccountType({{ $user->id }}, 'owner')" wire:confirm="{{ __('Make :name an owner account? They will need owner verification before listing.', ['name' => $user->name]) }}">
                                        {{ __('Make owner') }}
                                    </flux:button>
                                @elseif ($user->isOwner() && $user->vehicles_count === 0)
                                    <flux:button size="sm" wire:click="changeAccountType({{ $user->id }}, 'renter')" wire:confirm="{{ __('Make :name a renter account?', ['name' => $user->name]) }}">
                                        {{ __('Make renter') }}
                                    </flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </x-app.content>
</div>
