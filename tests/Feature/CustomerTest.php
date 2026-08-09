<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;

test('customer routes require authentication', function () {
    $customer = Customer::factory()->create();

    $this->getJson('/api/customer/' . $customer->id)->assertStatus(401);
    $this->getJson('/api/customer/tabledata')->assertStatus(401);
    $this->getJson('/api/customer/balance/' . $customer->id)->assertStatus(401);
    $this->postJson('/api/customer/save', [])->assertStatus(401);
});

test('get returns a single customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id, 'name' => 'Acme Corp']);

    $response = $this->actingAs($user)->getJson('/api/customer/' . $customer->id);

    $response->assertOk();
    $response->assertJsonPath('customer.name', 'Acme Corp');
});

test('get cannot return another user\'s customer', function () {
    $user = User::factory()->create();
    $otherUsersCustomer = Customer::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/customer/' . $otherUsersCustomer->id);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save creates a new customer when id is 0', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/customer/save', [
        'id' => 0,
        'name' => 'New Customer LLC',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $this->assertDatabaseHas('customer', ['name' => 'New Customer LLC', 'user_id' => $user->id]);
});

test('save updates an existing customer', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

    $response = $this->actingAs($user)->postJson('/api/customer/save', [
        'id' => $customer->id,
        'name' => 'New Name',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('customer', ['id' => $customer->id, 'name' => 'New Name']);
    $this->assertDatabaseCount('customer', 1);
});

test('save cannot update another user\'s customer', function () {
    $user = User::factory()->create();
    $otherUsersCustomer = Customer::factory()->create(['name' => 'Untouched']);

    $response = $this->actingAs($user)->postJson('/api/customer/save', [
        'id' => $otherUsersCustomer->id,
        'name' => 'Hijacked',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
    $this->assertDatabaseHas('customer', ['id' => $otherUsersCustomer->id, 'name' => 'Untouched']);
});

test('get fails gracefully for a nonexistent customer', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/customer/999999');

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save fails gracefully when updating a nonexistent customer', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/customer/save', [
        'id' => 999999,
        'name' => 'Ghost Customer',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save rejects a name longer than 64 characters', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/customer/save', [
        'id' => 0,
        'name' => str_repeat('x', 65),
    ]);

    $response->assertJsonPath('status', 'failed');
    $response->assertJsonStructure(['errors' => ['name']]);
});

test('getTableData only includes the authenticated user\'s customers', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id, 'name' => 'Acme Corp']);
    Invoice::factory()->create(['user_id' => $user->id, 'customerId' => $customer->id, 'amount' => 100]);
    Payment::factory()->create(['user_id' => $user->id, 'customerId' => $customer->id, 'amount' => 40]);

    Customer::factory()->create(['name' => 'Someone Else Inc']);

    $response = $this->actingAs($user)->getJson('/api/customer/tabledata');

    $response->assertOk();
    $rows = collect($response->json('customers'));

    expect($rows->pluck('name'))->not->toContain('Someone Else Inc');

    $row = $rows->firstWhere('name', 'Acme Corp');
    expect($row)->not->toBeNull();
    expect((float) $row['balance'])->toBe(-60.0);
});

test('getBalanceData returns merged transactions with a running balance', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    Invoice::factory()->create([
        'user_id' => $user->id,
        'customerId' => $customer->id,
        'amount' => 100,
        'date' => '2026-01-01',
    ]);
    Payment::factory()->create([
        'user_id' => $user->id,
        'customerId' => $customer->id,
        'amount' => 40,
        'date' => '2026-01-02',
    ]);

    $response = $this->actingAs($user)->getJson('/api/customer/balance/' . $customer->id);

    $response->assertOk();
    expect((float) $response->json('invTotal'))->toBe(-100.0);
    expect((float) $response->json('pmtTotal'))->toBe(40.0);

    $transactions = $response->json('transactions');
    expect($transactions)->toHaveCount(2);
    // running balance after both entries: -100 (invoice) + 40 (payment)
    expect((float) $transactions[1]['balance'])->toBe(-60.0);
});

test('getBalanceData cannot return another user\'s customer data', function () {
    $user = User::factory()->create();
    $otherUsersCustomer = Customer::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/customer/balance/' . $otherUsersCustomer->id);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});
