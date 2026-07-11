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
    $older = Invoice::factory()->create();
    $newer = Invoice::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/invoice');

    $response->assertOk();
    $ids = collect($response->json('invoices'))->pluck('id');
    expect($ids->first())->toBe((string) $newer->id);
    expect($ids->last())->toBe((string) $older->id);
});

test('get returns a single invoice', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['note' => 'Net 30']);

    $response = $this->actingAs($user)->getJson('/api/invoice/' . $invoice->id);

    $response->assertOk();
    $response->assertJsonPath('invoice.note', 'Net 30');
});

test('save creates a new invoice when id is 0', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/invoice/save', [
        'id' => 0,
        'customerId' => $customer->id,
        'date' => '2026-01-15',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('invoice', ['customerId' => $customer->id, 'date' => '2026-01-15']);
});

test('save updates an existing invoice', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['note' => 'Original']);

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

test('save rejects a missing date', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/invoice/save', [
        'id' => 0,
        'customerId' => $customer->id,
    ]);

    $response->assertJsonPath('status', 'failed');
    $response->assertJsonStructure(['errors' => ['date']]);
});

test('toggleSent flips the emailed flag', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['emailed' => 'N']);

    $response = $this->actingAs($user)->getJson('/api/invoice/sent/' . $invoice->id);
    $response->assertOk();
    $response->assertJsonPath('invoice.emailed', 'Y');

    $this->actingAs($user)->getJson('/api/invoice/sent/' . $invoice->id);
    expect($invoice->fresh()->emailed)->toBe('N');
});

test('customer route only returns that customer\'s invoices', function () {
    $user = User::factory()->create();
    $customerA = Customer::factory()->create();
    $customerB = Customer::factory()->create();
    Invoice::factory()->create(['customerId' => $customerA->id]);
    Invoice::factory()->create(['customerId' => $customerB->id]);

    $response = $this->actingAs($user)->getJson('/api/invoice/customer/' . $customerA->id);

    $response->assertOk();
    $invoices = $response->json('invoices');
    expect($invoices)->toHaveCount(1);
    expect($invoices[0]['customerId'])->toBe((string) $customerA->id);
});
