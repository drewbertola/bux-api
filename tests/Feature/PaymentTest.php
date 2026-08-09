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
    $older = Payment::factory()->create(['user_id' => $user->id]);
    $newer = Payment::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/payment');

    $response->assertOk();
    $ids = collect($response->json('payments'))->pluck('id');
    expect($ids->first())->toBe((string) $newer->id);
    expect($ids->last())->toBe((string) $older->id);
});

test('index does not include another user\'s payments', function () {
    $user = User::factory()->create();
    Payment::factory()->create(['user_id' => $user->id]);
    Payment::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/payment');

    $response->assertOk();
    expect($response->json('payments'))->toHaveCount(1);
});

test('get returns a single payment', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->create(['user_id' => $user->id, 'number' => 'CHK-1001']);

    $response = $this->actingAs($user)->getJson('/api/payment/' . $payment->id);

    $response->assertOk();
    $response->assertJsonPath('payment.number', 'CHK-1001');
});

test('get cannot return another user\'s payment', function () {
    $user = User::factory()->create();
    $otherUsersPayment = Payment::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/payment/' . $otherUsersPayment->id);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save creates a new payment when id is 0', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/payment/save', [
        'id' => 0,
        'customerId' => $customer->id,
        'date' => '2026-01-15',
        'method' => 'Card',
        'amount' => 50,
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('payment', ['customerId' => $customer->id, 'method' => 'Card', 'user_id' => $user->id]);
});

test('save rejects creating a payment against another user\'s customer', function () {
    $user = User::factory()->create();
    $otherUsersCustomer = Customer::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/payment/save', [
        'id' => 0,
        'customerId' => $otherUsersCustomer->id,
        'date' => '2026-01-15',
        'method' => 'Card',
        'amount' => 50,
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
    $this->assertDatabaseMissing('payment', ['customerId' => $otherUsersCustomer->id]);
});

test('save updates an existing payment', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $payment = Payment::factory()->create(['user_id' => $user->id, 'customerId' => $customer->id, 'method' => 'Cash']);

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

test('save cannot update another user\'s payment', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $otherUsersPayment = Payment::factory()->create(['method' => 'Cash']);

    $response = $this->actingAs($user)->postJson('/api/payment/save', [
        'id' => $otherUsersPayment->id,
        'customerId' => $customer->id,
        'date' => '2026-01-15',
        'method' => 'Transfer',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
    $this->assertDatabaseHas('payment', ['id' => $otherUsersPayment->id, 'method' => 'Cash']);
});

test('get fails gracefully for a nonexistent payment', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/payment/999999');

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save fails gracefully when updating a nonexistent payment', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

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
    $customer = Customer::factory()->create(['user_id' => $user->id]);

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
    $customerA = Customer::factory()->create(['user_id' => $user->id]);
    $customerB = Customer::factory()->create(['user_id' => $user->id]);
    Payment::factory()->create(['user_id' => $user->id, 'customerId' => $customerA->id]);
    Payment::factory()->create(['user_id' => $user->id, 'customerId' => $customerB->id]);

    $response = $this->actingAs($user)->getJson('/api/payment/customer/' . $customerA->id);

    $response->assertOk();
    $payments = $response->json('payments');
    expect($payments)->toHaveCount(1);
    expect($payments[0]['customerId'])->toBe((string) $customerA->id);
});

test('customer route does not include another user\'s payments', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    Payment::factory()->create(['user_id' => $user->id, 'customerId' => $customer->id]);
    Payment::factory()->create(['customerId' => $customer->id]);

    $response = $this->actingAs($user)->getJson('/api/payment/customer/' . $customer->id);

    $response->assertOk();
    expect($response->json('payments'))->toHaveCount(1);
});
