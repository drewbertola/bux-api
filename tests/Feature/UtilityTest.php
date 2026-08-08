<?php

use App\Models\Customer;
use App\Models\User;

test('completions requires authentication', function () {
    $response = $this->getJson('/api/completions');

    $response->assertStatus(401);
});

test('completions returns customer names and the static methods list', function () {
    $user = User::factory()->create();
    Customer::factory()->create(['userId' => $user->id, 'name' => 'Acme Corp']);

    $response = $this->actingAs($user)->getJson('/api/completions');

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonFragment(['label' => 'Acme Corp']);
    expect($response->json('methods'))->toHaveCount(4);
});

test('completions only returns the authenticated user\'s customers', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Customer::factory()->create(['userId' => $user->id, 'name' => 'Mine']);
    Customer::factory()->create(['userId' => $other->id, 'name' => 'Theirs']);

    $response = $this->actingAs($user)->getJson('/api/completions');

    $response->assertOk();
    $names = collect($response->json('customerNames'))->pluck('label');
    expect($names)->toContain('Mine');
    expect($names)->not->toContain('Theirs');
});
