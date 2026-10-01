<?php

use App\Actions\Payments\MarkPaymentRefunded;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Payments')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $method = '';

    #[Url(except: '')]
    public string $search = '';

    /**
     * The payments matching the filters, applied to a fresh query.
     *
     * @return Builder<Payment>
     */
    protected function filtered(): Builder
    {
        return Payment::query()
            ->when(PaymentStatus::tryFrom($this->status), fn (Builder $query, PaymentStatus $status) => $query->where('status', $status))
            ->when(PaymentMethod::tryFrom($this->method), fn (Builder $query, PaymentMethod $method) => $query->where('method', $method))
            ->when(filled($this->search), fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('reference', 'like', '%'.trim($this->search).'%')
                ->orWhereHas('booking', fn (Builder $query) => $query->where('reference', 'like', '%'.trim($this->search).'%'))));
    }

    /**
     * Every payment attempt matching the filters, newest first.
     *
     * @return LengthAwarePaginator<int, Payment>
     */
    #[Computed]
    public function payments(): LengthAwarePaginator
    {
        return $this->filtered()->with('booking.renter')->latest()->paginate(20);
    }

    /**
     * Totals for the filtered payments.
     *
     * @return array{count: int, collected: float, refundDue: int}
     */
    #[Computed]
    public function totals(): array
    {
        return [
            'count' => $this->filtered()->count(),
            'collected' => (int) $this->filtered()->where('status', PaymentStatus::Paid)->sum('amount') / 100,
            'refundDue' => Payment::where('status', PaymentStatus::RefundDue)->count(),
        ];
    }

    /**
     * Go back to the first page whenever the filters change.
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'method', 'search'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Record that a refund was issued.
     */
    public function markRefunded(int $paymentId, MarkPaymentRefunded $markPaymentRefunded): void
    {
        $markPaymentRefunded->handle(Payment::findOrFail($paymentId), Auth::user());

        unset($this->payments, $this->totals);

        Flux::toast(variant: 'success', text: __('Payment marked as refunded.'));
    }
}; ?>

<div>
    <x-app.page-header :title="__('Payments')" :description="__('Every payment attempt on CarHub, with what was collected and what is owed back.')" />

    <x-app.content class="space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-dashboard.stat :label="__('Payments shown')" :value="number_format($this->totals['count'])" />
            <x-dashboard.stat :label="__('Collected (paid)')" :value="'₱'.number_format($this->totals['collected'], 2)" />
            <x-dashboard.stat
                :label="__('Refunds due')"
                :value="number_format($this->totals['refundDue'])"
                :tone="$this->totals['refundDue'] > 0 ? 'attention' : 'default'"
                :hint="__('Issue in the PayMongo dashboard, then mark refunded here.')"
            />
        </div>

        <div class="grid gap-4 sm:grid-cols-[1fr_12rem_12rem]">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Payment or booking reference')" clearable />
            <flux:select wire:model.live="status">
                <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
                @foreach (PaymentStatus::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="method">
                <flux:select.option value="">{{ __('Any method') }}</flux:select.option>
                @foreach (PaymentMethod::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:error name="refund" />

        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white px-4 sm:px-6">
            <flux:table :paginate="$this->payments">
                <flux:table.columns>
                    <flux:table.column>{{ __('Payment') }}</flux:table.column>
                    <flux:table.column>{{ __('Booking') }}</flux:table.column>
                    <flux:table.column>{{ __('Method') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->payments as $payment)
                        <flux:table.row :key="$payment->id">
                            <flux:table.cell>
                                <div class="font-medium text-zinc-900">{{ $payment->reference }}</div>
                                <div class="text-xs text-zinc-500">{{ $payment->created_at?->format('M j, Y g:i A') }} &middot; {{ $payment->provider }}</div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <a href="{{ route('admin.bookings.show', $payment->booking) }}" wire:navigate class="text-zinc-900 hover:text-brand-700">{{ $payment->booking->reference }}</a>
                                <div class="text-xs text-zinc-500">{{ $payment->booking->renter->name }}</div>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-600">{{ $payment->method->label() }}</flux:table.cell>
                            <flux:table.cell align="end" class="font-medium text-zinc-900 tabular-nums">&#8369;{{ number_format($payment->amountInPesos(), 2) }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$payment->status->color()">{{ $payment->status->label() }}</flux:badge>
                                @if ($payment->failure_reason)
                                    <div class="mt-1 max-w-56 text-xs text-zinc-500">{{ $payment->failure_reason }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell align="end">
                                @if ($payment->status === PaymentStatus::RefundDue)
                                    <flux:button size="sm" wire:click="markRefunded({{ $payment->id }})" wire:confirm="{{ __('Confirm the refund was issued in the payment provider\'s dashboard?') }}">
                                        {{ __('Mark refunded') }}
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
