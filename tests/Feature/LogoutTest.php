<?php

use App\Models\User;

test('whoami reflects the authenticated user and issues a fresh token', function () {
    // /whoami sits outside auth:sanctum and reads the default (session/web)
    // guard directly, so it reflects session auth, not bearer tokens.
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/whoami');

    $response->assertJsonPath('user.email', $user->email);
    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

test('whoami returns an empty payload as a guest', function () {
    $response = $this->getJson('/api/whoami');

    $response->assertOk();
    expect($response->json('user'))->toBeNull();
    expect($response->json('token'))->toBeNull();
});

test('logout revokes the current token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test');

    $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
        ->postJson('/api/logout')
        ->assertOk();

    // Sanctum's guard caches its resolved user for the lifetime of the
    // container, so a follow-up simulated request within the same test
    // would still read as authenticated even though the token is gone —
    // that's a test-harness quirk (there's no such caching across real,
    // separate HTTP requests/processes), so revocation is verified against
    // the database directly instead.
    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->accessToken->id,
    ]);
});
