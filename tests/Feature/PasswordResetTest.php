<?php

use App\Mail\ForgotPasswordEmail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

test('requesting a reset code emails the user and stores a verification code', function () {
    Mail::fake();
    $user = User::factory()->create(['verification_code' => '']);

    $response = $this->postJson('/api/forgot', ['email' => $user->email]);

    $response->assertOk();
    $response->assertJsonPath('status', 'success');

    $user->refresh();
    expect($user->verification_code)->not->toBe('');

    Mail::assertSent(ForgotPasswordEmail::class, fn ($mail) => $mail->hasTo($user->email));
});

test('requesting a reset code for an unknown email responds the same as a known one, without sending mail', function () {
    Mail::fake();

    $response = $this->postJson('/api/forgot', ['email' => 'nobody@example.com']);

    // the response must not reveal whether the address is registered —
    // otherwise this endpoint becomes an account enumeration oracle
    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    Mail::assertNothingSent();
});

test('requesting a reset code sets an expiry', function () {
    Mail::fake();
    $user = User::factory()->create();

    $this->postJson('/api/forgot', ['email' => $user->email])->assertOk();

    $user->refresh();
    expect($user->verification_code_expires_at)->not->toBeNull();
    expect($user->verification_code_expires_at->isFuture())->toBeTrue();
});

test('an account that never requested a code cannot be reset with a blank token', function () {
    // a fresh account's verification_code defaults to '' — an empty
    // token must never be treated as "correct" for it
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    $response = $this->postJson('/api/update-password', [
        'email' => $user->email,
        'token' => '',
        'newPassword' => 'new-password-1',
        'newPassword2' => 'new-password-1',
    ]);

    $response->assertJsonPath('status', 'failed');

    $user->refresh();
    expect(Hash::check('old-password', $user->password))->toBeTrue();
});

test('resetting the password fails with an expired code', function () {
    $user = User::factory()->create([
        'verification_code' => 'ABC12345',
        'verification_code_expires_at' => now()->subMinute(),
        'password' => Hash::make('old-password'),
    ]);

    $response = $this->postJson('/api/update-password', [
        'email' => $user->email,
        'token' => 'ABC12345',
        'newPassword' => 'new-password-1',
        'newPassword2' => 'new-password-1',
    ]);

    $response->assertJsonPath('status', 'failed');

    $user->refresh();
    expect(Hash::check('old-password', $user->password))->toBeTrue();
});

test('a guest can reset their password with a valid, unexpired code', function () {
    $user = User::factory()->create([
        'verification_code' => 'ABC12345',
        'verification_code_expires_at' => now()->addMinutes(10),
        'password' => Hash::make('old-password'),
    ]);

    $response = $this->postJson('/api/update-password', [
        'email' => $user->email,
        'token' => 'ABC12345',
        'newPassword' => 'new-password-1',
        'newPassword2' => 'new-password-1',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'success');

    $user->refresh();
    expect($user->verification_code)->toBe('');
    expect(Hash::check('new-password-1', $user->password))->toBeTrue();
});

test('resetting the password fails with the wrong code', function () {
    $user = User::factory()->create([
        'verification_code' => 'ABC12345',
        'password' => Hash::make('old-password'),
    ]);

    $response = $this->postJson('/api/update-password', [
        'email' => $user->email,
        'token' => 'WRONGCODE',
        'newPassword' => 'new-password-1',
        'newPassword2' => 'new-password-1',
    ]);

    $response->assertJsonPath('status', 'failed');

    $user->refresh();
    expect(Hash::check('old-password', $user->password))->toBeTrue();
});

test('resetting the password fails when the new passwords do not match', function () {
    $user = User::factory()->create(['verification_code' => 'ABC12345']);

    $response = $this->postJson('/api/update-password', [
        'email' => $user->email,
        'token' => 'ABC12345',
        'newPassword' => 'new-password-1',
        'newPassword2' => 'different-password',
    ]);

    $response->assertJsonPath('status', 'failed');
    $response->assertJsonStructure(['errors' => ['newPassword2']]);
});

test('an authenticated user can change their password by supplying their current one', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $response = $this->actingAs($user)->postJson('/api/update-password', [
        'email' => $user->email,
        'password' => 'old-password',
        'newPassword' => 'new-password-1',
        'newPassword2' => 'new-password-1',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'success');

    $user->refresh();
    expect(Hash::check('new-password-1', $user->password))->toBeTrue();
});

test('an authenticated user cannot change their password without supplying the current one', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $response = $this->actingAs($user)->postJson('/api/update-password', [
        'email' => $user->email,
        'newPassword' => 'new-password-1',
        'newPassword2' => 'new-password-1',
    ]);

    $response->assertJsonPath('status', 'failed');

    $user->refresh();
    expect(Hash::check('old-password', $user->password))->toBeTrue();
});

test('an authenticated user cannot change their password with the wrong current password', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $response = $this->actingAs($user)->postJson('/api/update-password', [
        'email' => $user->email,
        'password' => 'not-the-right-password',
        'newPassword' => 'new-password-1',
        'newPassword2' => 'new-password-1',
    ]);

    $response->assertJsonPath('status', 'failed');

    $user->refresh();
    expect(Hash::check('old-password', $user->password))->toBeTrue();
});

test('changing the password revokes the user\'s existing tokens', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);
    $token = $user->createToken('test');

    $this->actingAs($user)->postJson('/api/update-password', [
        'email' => $user->email,
        'password' => 'old-password',
        'newPassword' => 'new-password-1',
        'newPassword2' => 'new-password-1',
    ])->assertOk();

    // a token issued before the password change must not survive it —
    // otherwise a stolen bearer token would outlive the user's own
    // remediation
    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->accessToken->id,
    ]);
});
