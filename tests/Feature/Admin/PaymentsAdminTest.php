<?php

use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('administrators see every payment attempt with totals', function () {
    $paid = Payment::factory()->paid()->create(['amount' => 460000]);
    Payment::factory()->create(['status' => PaymentStatus::Failed]);

    $component = Livewire::actingAs($this->admin)->test('pages::admin.payments.index')->assertSee($paid->reference);

    expect($component->instance()->totals)->toMatchArray(['count' => 2, 'collected' => 4600.0, 'refundDue' => 0]);

    $component->set('status', 'failed');

    expect($component->instance()->payments->total())->toBe(1);
});

test('a refund due can be marked as refunded', function () {
    $payment = Payment::factory()->create(['status' => PaymentStatus::RefundDue, 'failure_reason' => 'Paid after expiry.']);

    Livewire::actingAs($this->admin)
        ->test('pages::admin.payments.index')
        ->assertSee('Mark refunded')
        ->call('markRefunded', $payment->id)
        ->assertHasNoErrors();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and(ActivityLog::where('action', 'payment.refunded')->sole()->actor_id)->toBe($this->admin->id);
});

test('only refunds that are due can be marked refunded', function () {
    $payment = Payment::factory()->paid()->create();

    Livewire::actingAs($this->admin)
        ->test('pages::admin.payments.index')
        ->call('markRefunded', $payment->id)
        ->assertHasErrors('refund');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid);
});

test('only administrators see payments', function () {
    $this->actingAs(User::factory()->verifiedOwner()->create())->get(route('admin.payments.index'))->assertForbidden();
});
