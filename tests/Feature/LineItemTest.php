<?php

use App\Models\Invoice;
use App\Models\LineItem;
use App\Models\User;

test('line item routes require authentication', function () {
    $lineItem = LineItem::factory()->create();

    $this->getJson('/api/line_item/invoice/' . $lineItem->invoiceId)->assertStatus(401);
    $this->getJson('/api/line_item/' . $lineItem->id)->assertStatus(401);
    $this->postJson('/api/line_item/save', [])->assertStatus(401);
});

test('index lists line items for an invoice only', function () {
    $user = User::factory()->create();
    $invoiceA = Invoice::factory()->create();
    $invoiceB = Invoice::factory()->create();
    LineItem::factory()->create(['invoiceId' => $invoiceA->id]);
    LineItem::factory()->create(['invoiceId' => $invoiceB->id]);

    $response = $this->actingAs($user)->getJson('/api/line_item/invoice/' . $invoiceA->id);

    $response->assertOk();
    $lineItems = $response->json('lineItems');
    expect($lineItems)->toHaveCount(1);
    expect($lineItems[0]['invoiceId'])->toBe((string) $invoiceA->id);
});

test('get returns a single line item', function () {
    $user = User::factory()->create();
    $lineItem = LineItem::factory()->create(['description' => 'Widget']);

    $response = $this->actingAs($user)->getJson('/api/line_item/' . $lineItem->id);

    $response->assertOk();
    $response->assertJsonPath('lineItem.description', 'Widget');
});

test('save creates a new line item when id is 0 and recalculates the invoice amount', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['amount' => 0]);

    $response = $this->actingAs($user)->postJson('/api/line_item/save', [
        'id' => 0,
        'invoiceId' => $invoice->id,
        'price' => 10,
        'quantity' => 3,
        'description' => 'Widget',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('line_item', ['invoiceId' => $invoice->id, 'description' => 'Widget']);
    expect((float) $invoice->fresh()->amount)->toBe(30.0);
});

test('save updates an existing line item and recalculates the invoice amount', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create();
    $lineItem = LineItem::factory()->create([
        'invoiceId' => $invoice->id,
        'price' => 10,
        'quantity' => 1,
    ]);

    $response = $this->actingAs($user)->postJson('/api/line_item/save', [
        'id' => $lineItem->id,
        'invoiceId' => $invoice->id,
        'price' => 10,
        'quantity' => 5,
        'description' => $lineItem->description,
    ]);

    $response->assertOk();
    $this->assertDatabaseCount('line_item', 1);
    expect((float) $invoice->fresh()->amount)->toBe(50.0);
});

test('get fails gracefully for a nonexistent line item', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/line_item/999999');

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save fails gracefully when updating a nonexistent line item', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/line_item/save', [
        'id' => 999999,
        'invoiceId' => $invoice->id,
        'price' => 10,
        'quantity' => 1,
        'description' => 'Ghost item',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save rejects a description longer than 64 characters', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/line_item/save', [
        'id' => 0,
        'invoiceId' => $invoice->id,
        'description' => str_repeat('x', 65),
    ]);

    $response->assertJsonPath('status', 'failed');
    $response->assertJsonStructure(['errors' => ['description']]);
});
