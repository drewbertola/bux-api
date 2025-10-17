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

        $data = ['verification_code' => VerificationCodeService::generate()];

        $user->update($data);

        Mail::to($user->email)->send(new ForgotPasswordEmail($user));

        return $this->success(
            [],
            'A password recovery email has been sent.  Please check your ' .
            'inbox and spam folders.'
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

            if (empty($request->only('token'))) {
                return $this->error(['errors' => [
                    'errors' => ['token' => ['The code (from our email) was not entered.']]
                ]], 'One or more errors were encountered.');
            } elseif ($user->verification_code !== $request->input('token')) {
                return $this->error([
                    'errors' => ['token' => ['The code did not match our records.']],
                ], 'One or more errors were encountered.');
            }
        }

        $data = [];
        $data['password'] = $newPassword;
        $data['verification_code'] = '';

        $user->update($data);

        Auth::logout();

        $request->session()->invalidate();

        return $this->success([], '', 200);
    }

    public function logout(Request $request)
    {
        $user = User::find(Auth::id());

        //$user->currentAccessToken()->delete();

        Auth::logout();
        $request->session()->invalidate();

        return $this->success([], 'You have been logged out.');
    }

    public function whoami()
    {
        $user = Auth::user();

        if ($user) {
           return $this->success([
                'user' => $user,
                'token' => $user->createToken('Auth token for ' . $user->name)->plainTextToken,
            ]);
        } else {
            return $this->success([]);
        }
    }
}
