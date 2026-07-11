<?php

use App\Models\Customer;
use App\Models\User;

test('completions requires authentication', function () {
    $response = $this->getJson('/api/completions');

    $response->assertStatus(401);
});

test('completions returns customer names and the static methods list', function () {
    $user = User::factory()->create();
    Customer::factory()->create(['name' => 'Acme Corp']);

    $response = $this->actingAs($user)->getJson('/api/completions');

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonFragment(['label' => 'Acme Corp']);
    expect($response->json('methods'))->toHaveCount(4);
});
