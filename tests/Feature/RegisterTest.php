<?php

use App\Models\User;

test('a new user can register', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password2' => 'password123',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('user.email', 'jane@example.com');
    expect($response->json('token'))->toBeString()->not->toBeEmpty();
    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});

test('registration fails when the email is already taken', function () {
    User::factory()->create(['email' => 'jane@example.com']);

    $response = $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password2' => 'password123',
    ]);

    $response->assertJsonPath('status', 'failed');
    $response->assertJsonStructure(['errors' => ['email']]);
});

test('registration fails when the passwords do not match', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password2' => 'password456',
    ]);

    $response->assertJsonPath('status', 'failed');
    $response->assertJsonStructure(['errors' => ['password2']]);
    expect(User::where('email', 'jane@example.com')->exists())->toBeFalse();
});

test('registration fails when the password is too short', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'short',
        'password2' => 'short',
    ]);

    $response->assertJsonPath('status', 'failed');
    $response->assertJsonStructure(['errors' => ['password']]);
});
