<?php

use Livewire\Livewire;

test('the contact form rejects an empty submission', function () {
    Livewire::test('pages::marketing.contact')
        ->call('send')
        ->assertHasErrors(['name', 'email', 'subject', 'message']);
});

test('the contact form rejects an invalid email address', function () {
    Livewire::test('pages::marketing.contact')
        ->set('name', 'Juan dela Cruz')
        ->set('email', 'not-an-email')
        ->set('subject', 'booking')
        ->set('message', 'I would like to ask about extending an active booking.')
        ->call('send')
        ->assertHasErrors(['email' => 'email']);
});

test('the contact form rejects a message that is too short', function () {
    Livewire::test('pages::marketing.contact')
        ->set('name', 'Juan dela Cruz')
        ->set('email', 'juan@example.com')
        ->set('subject', 'booking')
        ->set('message', 'Too short.')
        ->call('send')
        ->assertHasErrors(['message' => 'min']);
});

test('the contact form rejects a subject outside the offered topics', function () {
    Livewire::test('pages::marketing.contact')
        ->set('name', 'Juan dela Cruz')
        ->set('email', 'juan@example.com')
        ->set('subject', 'something-made-up')
        ->set('message', 'I would like to ask about extending an active booking.')
        ->call('send')
        ->assertHasErrors(['subject' => 'in']);
});

test('a valid enquiry is accepted and clears the form', function () {
    Livewire::test('pages::marketing.contact')
        ->set('name', 'Juan dela Cruz')
        ->set('email', 'juan@example.com')
        ->set('subject', 'hosting')
        ->set('message', 'I have a 2021 Toyota Innova and would like to list it on CarHub.')
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('name', '')
        ->assertSet('email', '')
        ->assertSet('subject', '')
        ->assertSet('message', '');
});
