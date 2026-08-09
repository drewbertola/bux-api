<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;

test('invoice routes require authentication', function () {
    $invoice = Invoice::factory()->create();

    $this->getJson('/api/invoice')->assertStatus(401);
    $this->getJson('/api/invoice/' . $invoice->id)->assertStatus(401);
    $this->postJson('/api/invoice/save', [])->assertStatus(401);
    $this->getJson('/api/invoice/sent/' . $invoice->id)->assertStatus(401);
    $this->getJson('/api/invoice/customer/1')->assertStatus(401);
});

test('index lists invoices newest first', function () {
    $user = User::factory()->create();
    $older = Invoice::factory()->create(['user_id' => $user->id]);
    $newer = Invoice::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/invoice');

    $response->assertOk();
    $ids = collect($response->json('invoices'))->pluck('id');
    expect($ids->first())->toBe((string) $newer->id);
    expect($ids->last())->toBe((string) $older->id);
});

test('index does not include another user\'s invoices', function () {
    $user = User::factory()->create();
    Invoice::factory()->create(['user_id' => $user->id]);
    Invoice::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/invoice');

    $response->assertOk();
    expect($response->json('invoices'))->toHaveCount(1);
});

test('get returns a single invoice', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id, 'note' => 'Net 30']);

    $response = $this->actingAs($user)->getJson('/api/invoice/' . $invoice->id);

    $response->assertOk();
    $response->assertJsonPath('invoice.note', 'Net 30');
});

test('get cannot return another user\'s invoice', function () {
    $user = User::factory()->create();
    $otherUsersInvoice = Invoice::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/invoice/' . $otherUsersInvoice->id);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save creates a new invoice when id is 0', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/invoice/save', [
        'id' => 0,
        'customerId' => $customer->id,
        'date' => '2026-01-15',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('invoice', ['customerId' => $customer->id, 'date' => '2026-01-15', 'user_id' => $user->id]);
});

test('save rejects creating an invoice against another user\'s customer', function () {
    $user = User::factory()->create();
    $otherUsersCustomer = Customer::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/invoice/save', [
        'id' => 0,
        'customerId' => $otherUsersCustomer->id,
        'date' => '2026-01-15',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
    $this->assertDatabaseMissing('invoice', ['customerId' => $otherUsersCustomer->id]);
});

test('save updates an existing invoice', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $invoice = Invoice::factory()->create(['user_id' => $user->id, 'customerId' => $customer->id, 'note' => 'Original']);

    $response = $this->actingAs($user)->postJson('/api/invoice/save', [
        'id' => $invoice->id,
        'customerId' => $invoice->customerId,
        'date' => (string) $invoice->date,
        'note' => 'Updated',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('invoice', ['id' => $invoice->id, 'note' => 'Updated']);
    $this->assertDatabaseCount('invoice', 1);
});

test('save cannot update another user\'s invoice', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    $otherUsersInvoice = Invoice::factory()->create(['note' => 'Untouched']);

    $response = $this->actingAs($user)->postJson('/api/invoice/save', [
        'id' => $otherUsersInvoice->id,
        'customerId' => $customer->id,
        'date' => '2026-01-15',
        'note' => 'Hijacked',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
    $this->assertDatabaseHas('invoice', ['id' => $otherUsersInvoice->id, 'note' => 'Untouched']);
});

test('get fails gracefully for a nonexistent invoice', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/invoice/999999');

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save fails gracefully when updating a nonexistent invoice', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/invoice/save', [
        'id' => 999999,
        'customerId' => $customer->id,
        'date' => '2026-01-15',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('toggleSent fails gracefully for a nonexistent invoice', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/invoice/sent/999999');

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save rejects a missing date', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/invoice/save', [
        'id' => 0,
        'customerId' => $customer->id,
    ]);

    $response->assertJsonPath('status', 'failed');
    $response->assertJsonStructure(['errors' => ['date']]);
});

test('toggleSent flips the emailed flag', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id, 'emailed' => 'N']);

    $response = $this->actingAs($user)->getJson('/api/invoice/sent/' . $invoice->id);
    $response->assertOk();
    $response->assertJsonPath('invoice.emailed', 'Y');

    $this->actingAs($user)->getJson('/api/invoice/sent/' . $invoice->id);
    expect($invoice->fresh()->emailed)->toBe('N');
});

test('toggleSent cannot modify another user\'s invoice', function () {
    $user = User::factory()->create();
    $otherUsersInvoice = Invoice::factory()->create(['emailed' => 'N']);

    $response = $this->actingAs($user)->getJson('/api/invoice/sent/' . $otherUsersInvoice->id);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
    expect($otherUsersInvoice->fresh()->emailed)->toBe('N');
});

test('customer route only returns that customer\'s invoices', function () {
    $user = User::factory()->create();
    $customerA = Customer::factory()->create(['user_id' => $user->id]);
    $customerB = Customer::factory()->create(['user_id' => $user->id]);
    Invoice::factory()->create(['user_id' => $user->id, 'customerId' => $customerA->id]);
    Invoice::factory()->create(['user_id' => $user->id, 'customerId' => $customerB->id]);

    $response = $this->actingAs($user)->getJson('/api/invoice/customer/' . $customerA->id);

    $response->assertOk();
    $invoices = $response->json('invoices');
    expect($invoices)->toHaveCount(1);
    expect($invoices[0]['customerId'])->toBe((string) $customerA->id);
});

test('customer route does not include another user\'s invoices', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['user_id' => $user->id]);
    Invoice::factory()->create(['user_id' => $user->id, 'customerId' => $customer->id]);
    Invoice::factory()->create(['customerId' => $customer->id]);

    $response = $this->actingAs($user)->getJson('/api/invoice/customer/' . $customer->id);

    $response->assertOk();
    expect($response->json('invoices'))->toHaveCount(1);
});
