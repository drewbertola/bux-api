<?php

use App\Models\Customer;
use App\Models\Payment;
use App\Models\User;

test('payment routes require authentication', function () {
    $payment = Payment::factory()->create();

    $this->getJson('/api/payment')->assertStatus(401);
    $this->getJson('/api/payment/' . $payment->id)->assertStatus(401);
    $this->postJson('/api/payment/save', [])->assertStatus(401);
    $this->getJson('/api/payment/customer/1')->assertStatus(401);
});

test('index lists payments newest first', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['userId' => $user->id]);
    $older = Payment::factory()->create(['customerId' => $customer->id]);
    $newer = Payment::factory()->create(['customerId' => $customer->id]);

    $response = $this->actingAs($user)->getJson('/api/payment');

    $response->assertOk();
    $ids = collect($response->json('payments'))->pluck('id');
    expect($ids->first())->toBe((string) $newer->id);
    expect($ids->last())->toBe((string) $older->id);
});

test('index only lists the authenticated user\'s payments', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $mine = Customer::factory()->create(['userId' => $user->id]);
    $theirs = Customer::factory()->create(['userId' => $other->id]);
    $myPayment = Payment::factory()->create(['customerId' => $mine->id]);
    Payment::factory()->create(['customerId' => $theirs->id]);

    $response = $this->actingAs($user)->getJson('/api/payment');

    $response->assertOk();
    $ids = collect($response->json('payments'))->pluck('id');
    expect($ids->all())->toBe([(string) $myPayment->id]);
});

test('get returns a single payment', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['userId' => $user->id]);
    $payment = Payment::factory()->create(['customerId' => $customer->id, 'number' => 'CHK-1001']);

    $response = $this->actingAs($user)->getJson('/api/payment/' . $payment->id);

    $response->assertOk();
    $response->assertJsonPath('payment.number', 'CHK-1001');
});

test('a user cannot view another user\'s payment', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $customer = Customer::factory()->create(['userId' => $other->id]);
    $payment = Payment::factory()->create(['customerId' => $customer->id]);

    $response = $this->actingAs($user)->getJson('/api/payment/' . $payment->id);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save creates a new payment when id is 0', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['userId' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/payment/save', [
        'id' => 0,
        'customerId' => $customer->id,
        'date' => '2026-01-15',
        'method' => 'Card',
        'amount' => 50,
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('payment', ['customerId' => $customer->id, 'method' => 'Card']);
});

test('a user cannot create a payment against another user\'s customer', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $customer = Customer::factory()->create(['userId' => $other->id]);

    $response = $this->actingAs($user)->postJson('/api/payment/save', [
        'id' => 0,
        'customerId' => $customer->id,
        'date' => '2026-01-15',
        'method' => 'Card',
        'amount' => 50,
    ]);

    $response->assertJsonPath('status', 'failed');
    $this->assertDatabaseMissing('payment', ['customerId' => $customer->id]);
});

test('save updates an existing payment', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['userId' => $user->id]);
    $payment = Payment::factory()->create(['customerId' => $customer->id, 'method' => 'Cash']);

    $response = $this->actingAs($user)->postJson('/api/payment/save', [
        'id' => $payment->id,
        'customerId' => $payment->customerId,
        'date' => (string) $payment->date,
        'method' => 'Transfer',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('payment', ['id' => $payment->id, 'method' => 'Transfer']);
    $this->assertDatabaseCount('payment', 1);
});

test('a user cannot update another user\'s payment', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $customer = Customer::factory()->create(['userId' => $other->id]);
    $payment = Payment::factory()->create(['customerId' => $customer->id, 'method' => 'Cash']);

    $response = $this->actingAs($user)->postJson('/api/payment/save', [
        'id' => $payment->id,
        'customerId' => $payment->customerId,
        'date' => (string) $payment->date,
        'method' => 'Transfer',
    ]);

    $response->assertJsonPath('status', 'failed');
    $this->assertDatabaseHas('payment', ['id' => $payment->id, 'method' => 'Cash']);
});

test('get fails gracefully for a nonexistent payment', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/payment/999999');

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save fails gracefully when updating a nonexistent payment', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['userId' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/payment/save', [
        'id' => 999999,
        'customerId' => $customer->id,
        'date' => '2026-01-15',
        'method' => 'Card',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save rejects an invalid method', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['userId' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/payment/save', [
        'id' => 0,
        'customerId' => $customer->id,
        'date' => '2026-01-15',
        'method' => 'Bitcoin',
    ]);

    $response->assertJsonPath('status', 'failed');
    $response->assertJsonStructure(['errors' => ['method']]);
});

test('customer route only returns that customer\'s payments', function () {
    $user = User::factory()->create();
    $customerA = Customer::factory()->create(['userId' => $user->id]);
    $customerB = Customer::factory()->create(['userId' => $user->id]);
    Payment::factory()->create(['customerId' => $customerA->id]);
    Payment::factory()->create(['customerId' => $customerB->id]);

    $response = $this->actingAs($user)->getJson('/api/payment/customer/' . $customerA->id);

    $response->assertOk();
    $payments = $response->json('payments');
    expect($payments)->toHaveCount(1);
    expect($payments[0]['customerId'])->toBe((string) $customerA->id);
});

test('a user cannot list another user\'s payments by customer', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $customer = Customer::factory()->create(['userId' => $other->id]);
    Payment::factory()->create(['customerId' => $customer->id]);

    $response = $this->actingAs($user)->getJson('/api/payment/customer/' . $customer->id);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});
