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

test('registration is throttled per IP regardless of email address', function () {
    // the 'login' limiter only throttles by email, so a bot spinning up
    // many distinct throwaway accounts from one IP wouldn't be slowed
    // down by that alone — the 'register' limiter closes that gap
    for ($i = 0; $i < 20; $i++) {
        $this->postJson('/api/register', [
            'name' => 'Jane Doe',
            'email' => "jane{$i}@example.com",
            'password' => 'password123',
            'password2' => 'password123',
        ])->assertJsonPath('status', 'success');
    }

    $response = $this->postJson('/api/register', [
        'name' => 'Jane Doe',
        'email' => 'one-too-many@example.com',
        'password' => 'password123',
        'password2' => 'password123',
    ]);

    $response->assertJsonPath('status', 'Request failed.');
    expect(User::where('email', 'one-too-many@example.com')->exists())->toBeFalse();
});
