<?php

use App\Models\CallEvent;
use App\Models\VonageSetting;
use Livewire\Livewire;

test('the setup modal blocks the phone until every setting is added', function () {
    $this->get(route('phone'))
        ->assertOk()
        ->assertSee('Add your application ID')
        ->assertSee('inert', false);
});

test('the setup modal is not shown once every setting is added', function () {
    saveVonageSettings([...privateKeySettings(), ...answerphoneGreetingSettings(), ...recordingBucketSettings()]);

    $this->get(route('phone'))
        ->assertOk()
        ->assertDontSee('Set up your dialler')
        ->assertDontSee('inert', false);
});

test('the answerphone switch is saved', function () {
    Livewire::test('pages::phone')
        ->assertSet('answerphoneEnabled', false)
        ->set('answerphoneEnabled', true);

    expect(VonageSetting::current()->answerphone_enabled)->toBeTrue();
});

test('a client token is only issued once every setting is added', function () {
    Livewire::test('pages::phone')
        ->call('issueToken')
        ->assertReturned(null);

    saveVonageSettings(privateKeySettings());

    Livewire::test('pages::phone')
        ->call('issueToken')
        ->assertReturned(null);

    saveVonageSettings([...privateKeySettings(), ...answerphoneGreetingSettings(), ...recordingBucketSettings()]);

    Livewire::test('pages::phone')
        ->call('issueToken')
        ->assertReturned(fn (?string $token) => substr_count((string) $token, '.') === 2);
});

test('the console lists call events and can be cleared', function () {
    CallEvent::factory()->create(['message' => 'Incoming call from 447700900001']);

    Livewire::test('call-console')
        ->assertSee('Incoming call from 447700900001')
        ->call('clear')
        ->assertDontSee('Incoming call from 447700900001');

    expect(CallEvent::count())->toBe(0);
});
