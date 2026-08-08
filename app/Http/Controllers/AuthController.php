<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordResetCodeRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\RegisterRequest;
use App\Mail\ForgotPasswordEmail;
use App\Models\User;
use App\Services\VerificationCodeService;
use App\Traits\HttpResponses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    use HttpResponses;

    public function login(Request $request)
    {
        $validator = LoginRequest::validator($request->all());

        if ($validator->fails()) {
            return $this->error(['errors' => $validator->errors()], 'Error(s) were found.');
        }

        if (Auth::attempt($validator->safe(['email', 'password']))) {
            // user is valid
            $request->session()->regenerate();

            $user = User::find(Auth::id());

            // delete old tokens
            $user->tokens()->delete();

            return $this->success([
                'user' => Auth::user(),
                'token' => $user->createToken('Auth token for ' . $user->name)->plainTextToken,
            ]);
        }

        return $this->error(['errors' => ['email' => '', 'password' => '']], 'Invalid credentials.', 200);
    }

    public function register(Request $request)
    {
        $validator = RegisterRequest::validator($request->all());

        if ($validator->fails()) {
            return $this->error(['errors' => $validator->errors()], 'Error(s) were found.');
        }

        $password = $request->password;
        $password2 = $request->password2;
        if ($password !== $password2) {
            return $this->error([
                'errors' => ['password2' => 'Password and Confirm Password must match.']
            ], 'One or more errors were encountered.');
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($password),
        ]);

        return $this->success([
            'user' => $user,
            'token' => $user->createToken('Auth token for ' . $user->name)->plainTextToken,
        ]);
    }


    public function passwordResetCode(Request $request)
    {
        $validator = PasswordResetCodeRequest::validator($request->all());

        if ($validator->fails()) {
            return $this->error(['errors' => $validator->errors()], 'One or more errors were encountered.');
        }

        $user = User::where('email', $validator->safe()->only('email'))->first();

        // respond identically whether or not the address is registered —
        // only send mail (and burn a code) when there's actually a user,
        // so this endpoint can't be used to enumerate accounts
        if ($user) {
            $user->update([
                'verification_code' => VerificationCodeService::generate(),
                'verification_code_expires_at' => now()->addMinutes(30),
            ]);

            Mail::to($user->email)->send(new ForgotPasswordEmail($user));
        }

        return $this->success(
            [],
            'If that email address is in our system, a password recovery ' .
            'email has been sent.  Please check your inbox and spam folders.'
        );
    }

    public function changePassword(Request $request)
    {
        $validator = ChangePasswordRequest::validator($request->all());

        if ($validator->fails()) {
            return $this->error(['errors' => $validator->errors()], 'One or more errors were encountered.');
        }

        $newPassword = $request->input('newPassword');
        $newPassword2 = $request->input('newPassword2');

        if ($newPassword !== $newPassword2) {
            return $this->error([
                'errors' => ['newPassword2' => 'New Password and Confirm New Password must match.']
            ], 'One or more errors were encountered.');
        }

        // is this an update (not forgot password / reset)?
        $user = Auth::user();

        // no user, then it is a forgot password / reset
        if (empty($user)) {
            // check the code
            $user = User::where('email', $request->only('email'))->first();

            if (empty($user)) {
                return $this->error([
                    'errors' => ['email' => ['No match found in our records.']]
                ], 'The account could not be found.');
            }

            $token = (string) $request->input('token');

            if ($token === '') {
                return $this->error(['errors' => [
                    'errors' => ['token' => ['The code (from our email) was not entered.']]
                ]], 'One or more errors were encountered.');
            } elseif (empty($user->verification_code) || ! hash_equals($user->verification_code, $token)) {
                // the empty($user->verification_code) check matters on its
                // own: a freshly-created account's code defaults to '', and
                // without this a blank token would hash_equals('', '')
                // straight through as "correct" for any such account
                return $this->error([
                    'errors' => ['token' => ['The code did not match our records.']],
                ], 'One or more errors were encountered.');
            } elseif (empty($user->verification_code_expires_at) || $user->verification_code_expires_at->isPast()) {
                return $this->error([
                    'errors' => ['token' => ['This code has expired.  Please request a new one.']],
                ], 'One or more errors were encountered.');
            }
        } else {
            // authenticated self-service change: a session cookie or bearer
            // token alone must not be enough to take over the account, so
            // the caller has to prove they still know the current password
            $currentPassword = $request->input('password');

            if (empty($currentPassword)) {
                return $this->error([
                    'errors' => ['password' => ['Your current password is required.']]
                ], 'One or more errors were encountered.');
            }

            if (! Hash::check($currentPassword, $user->password)) {
                return $this->error([
                    'errors' => ['password' => ['Your current password is incorrect.']]
                ], 'One or more errors were encountered.');
            }
        }

        $data = [];
        $data['password'] = $newPassword;
        $data['verification_code'] = '';
        $data['verification_code_expires_at'] = null;

        $user->update($data);

        // revoke any outstanding tokens so a stolen bearer token can't
        // outlive a password change
        $user->tokens()->delete();

        Auth::logout();

        $request->session()->invalidate();

        return $this->success([], '', 200);
    }

    public function logout(Request $request)
    {
        // Delete all of the user's tokens rather than
        // Auth::user()->currentAccessToken(): Sanctum's guard checks the
        // session ('web') guard before the bearer token, and a
        // session-resolved user's "current" token is a TransientToken with
        // no delete() method at all — that would fatal-error here for any
        // request authenticated via the session cookie, which is the
        // common case for this app's browser-based SPA.
        //
        // Bare Auth::logout() isn't callable here: after auth:sanctum
        // authenticates, the default guard is 'sanctum' (a RequestGuard),
        // which has no logout() method. Target the 'web' guard directly —
        // the one actually backing the session — instead. Flushing the
        // session alone isn't enough either: it doesn't clear the guard's
        // already-resolved in-memory user for the rest of this request.
        User::find(Auth::id())?->tokens()->delete();

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        return $this->success([], 'You have been logged out.');
    }

    public function whoami()
    {
        $user = Auth::user();

        if ($user) {
            // mirror login()'s "one live token at a time" convention —
            // otherwise a client that polls this read-only endpoint would
            // accumulate an ever-growing, never-expiring set of valid
            // bearer tokens for the account
            $user->tokens()->delete();

           return $this->success([
                'user' => $user,
                'token' => $user->createToken('Auth token for ' . $user->name)->plainTextToken,
            ]);
        } else {
            return $this->success([]);
        }
    }
}
