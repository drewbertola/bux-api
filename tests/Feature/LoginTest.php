<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('a user can log in with correct credentials', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('user.email', $user->email);
    expect($response->json('token'))->toBeString()->not->toBeEmpty();
});

test('logging in revokes the user\'s prior tokens', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);
    $oldToken = $user->createToken('old');

    $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'correct-password',
    ])->assertOk();

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $oldToken->accessToken->id,
    ]);
});

test('a user cannot log in with the wrong password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('correct-password'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'failed');
    $response->assertJsonPath('message', 'Invalid credentials.');
});

test('a user cannot log in with an unknown email', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'nobody@example.com',
        'password' => 'whatever123',
    ]);

    $response->assertJsonPath('status', 'failed');
});

test('login requires a valid email and an 8+ character password', function () {
    $response = $this->postJson('/api/login', [
        'email' => 'not-an-email',
        'password' => 'short',
    ]);

    $response->assertJsonPath('status', 'failed');
    $response->assertJsonPath('message', 'Error(s) were found.');
    $response->assertJsonStructure(['errors' => ['email', 'password']]);
});
