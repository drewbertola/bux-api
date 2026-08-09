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
    $invoiceA = Invoice::factory()->create(['user_id' => $user->id]);
    $invoiceB = Invoice::factory()->create(['user_id' => $user->id]);
    LineItem::factory()->create(['user_id' => $user->id, 'invoiceId' => $invoiceA->id]);
    LineItem::factory()->create(['user_id' => $user->id, 'invoiceId' => $invoiceB->id]);

    $response = $this->actingAs($user)->getJson('/api/line_item/invoice/' . $invoiceA->id);

    $response->assertOk();
    $lineItems = $response->json('lineItems');
    expect($lineItems)->toHaveCount(1);
    expect($lineItems[0]['invoiceId'])->toBe((string) $invoiceA->id);
});

test('index does not include another user\'s line items', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id]);
    LineItem::factory()->create(['user_id' => $user->id, 'invoiceId' => $invoice->id]);
    LineItem::factory()->create(['invoiceId' => $invoice->id]);

    $response = $this->actingAs($user)->getJson('/api/line_item/invoice/' . $invoice->id);

    $response->assertOk();
    expect($response->json('lineItems'))->toHaveCount(1);
});

test('get returns a single line item', function () {
    $user = User::factory()->create();
    $lineItem = LineItem::factory()->create(['user_id' => $user->id, 'description' => 'Widget']);

    $response = $this->actingAs($user)->getJson('/api/line_item/' . $lineItem->id);

    $response->assertOk();
    $response->assertJsonPath('lineItem.description', 'Widget');
});

test('get cannot return another user\'s line item', function () {
    $user = User::factory()->create();
    $otherUsersLineItem = LineItem::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/line_item/' . $otherUsersLineItem->id);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save creates a new line item when id is 0 and recalculates the invoice amount', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id, 'amount' => 0]);

    $response = $this->actingAs($user)->postJson('/api/line_item/save', [
        'id' => 0,
        'invoiceId' => $invoice->id,
        'price' => 10,
        'quantity' => 3,
        'description' => 'Widget',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('line_item', ['invoiceId' => $invoice->id, 'description' => 'Widget', 'user_id' => $user->id]);
    expect((float) $invoice->fresh()->amount)->toBe(30.0);
});

test('save rejects creating a line item against another user\'s invoice', function () {
    $user = User::factory()->create();
    $otherUsersInvoice = Invoice::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/line_item/save', [
        'id' => 0,
        'invoiceId' => $otherUsersInvoice->id,
        'price' => 10,
        'quantity' => 3,
        'description' => 'Widget',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
    $this->assertDatabaseMissing('line_item', ['invoiceId' => $otherUsersInvoice->id]);
});

test('save updates an existing line item and recalculates the invoice amount', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id]);
    $lineItem = LineItem::factory()->create([
        'user_id' => $user->id,
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

test('save cannot update another user\'s line item', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id]);
    $otherUsersLineItem = LineItem::factory()->create(['description' => 'Untouched']);

    $response = $this->actingAs($user)->postJson('/api/line_item/save', [
        'id' => $otherUsersLineItem->id,
        'invoiceId' => $invoice->id,
        'price' => 10,
        'quantity' => 5,
        'description' => 'Hijacked',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
    $this->assertDatabaseHas('line_item', ['id' => $otherUsersLineItem->id, 'description' => 'Untouched']);
});

test('get fails gracefully for a nonexistent line item', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/line_item/999999');

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
});

test('save fails gracefully when updating a nonexistent line item', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->create(['user_id' => $user->id]);

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
    $invoice = Invoice::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/line_item/save', [
        'id' => 0,
        'invoiceId' => $invoice->id,
        'description' => str_repeat('x', 65),
    ]);

    $response->assertJsonPath('status', 'failed');
    $response->assertJsonStructure(['errors' => ['description']]);
});
