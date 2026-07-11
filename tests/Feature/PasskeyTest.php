<?php

use App\Models\User;

function fakePasskeyCredential(User $user, string $name): void
{
    $user->passkeys()->create([
        'name' => $name,
        'credential_id' => base64_encode(random_bytes(32)),
        'credential' => [
            'type' => 'public-key',
            'transports' => [],
            'attestationType' => 'none',
            'trustPath' => ['type' => 'Webauthn\\TrustPath\\EmptyTrustPath'],
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'credentialPublicKey' => base64_encode('fake-public-key'),
            'counter' => 0,
        ],
    ]);
}

test('protected passkey routes require authentication', function () {
    $this->getJson('/api/webauthn/passkeys')->assertStatus(401);
    $this->getJson('/api/webauthn/register/options')->assertStatus(401);
    $this->postJson('/api/webauthn/register', [])->assertStatus(401);
    $this->deleteJson('/api/webauthn/passkeys/1')->assertStatus(401);
});

test('login options are available to guests', function () {
    $response = $this->getJson('/api/webauthn/login/options');

    $response->assertOk();
    $response->assertJsonStructure(['options' => ['challenge', 'rpId']]);
});

test('a user only sees their own passkeys', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    fakePasskeyCredential($user, 'My Laptop');
    fakePasskeyCredential($other, 'Their Phone');

    $response = $this->actingAs($user)->getJson('/api/webauthn/passkeys');

    $response->assertOk();
    $passkeys = $response->json('passkeys');
    expect($passkeys)->toHaveCount(1);
    expect($passkeys[0]['name'])->toBe('My Laptop');
});

test('a user can delete their own passkey', function () {
    $user = User::factory()->create();
    fakePasskeyCredential($user, 'My Laptop');
    $passkey = $user->passkeys()->first();

    $response = $this->actingAs($user)
        ->deleteJson('/api/webauthn/passkeys/' . $passkey->id);

    $response->assertOk();
    $this->assertDatabaseMissing('passkeys', ['id' => $passkey->id]);
});

test('a user cannot delete another user\'s passkey', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    fakePasskeyCredential($other, 'Their Phone');
    $passkey = $other->passkeys()->first();

    $response = $this->actingAs($user)
        ->deleteJson('/api/webauthn/passkeys/' . $passkey->id);

    $response->assertStatus(403);
    $this->assertDatabaseHas('passkeys', ['id' => $passkey->id]);
});

