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

test('requesting a reset code fails for an unknown email', function () {
    Mail::fake();

    $response = $this->postJson('/api/forgot', ['email' => 'nobody@example.com']);

    $response->assertJsonPath('status', 'failed');
    Mail::assertNothingSent();
});

test('a guest can reset their password with a valid code', function () {
    $user = User::factory()->create([
        'verification_code' => 'ABC12345',
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

test('an authenticated user can change their password without a code', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);

    $response = $this->actingAs($user)->postJson('/api/update-password', [
        'email' => $user->email,
        'newPassword' => 'new-password-1',
        'newPassword2' => 'new-password-1',
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'success');

    $user->refresh();
    expect(Hash::check('new-password-1', $user->password))->toBeTrue();
});

test('changing the password revokes the user\'s existing tokens', function () {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);
    $token = $user->createToken('test');

    $this->actingAs($user)->postJson('/api/update-password', [
        'email' => $user->email,
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
