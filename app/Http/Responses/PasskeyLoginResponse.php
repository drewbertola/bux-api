<?php

namespace App\Http\Responses;

use App\Models\User;
use App\Traits\HttpResponses;
use Illuminate\Support\Facades\Auth;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;

class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    use HttpResponses;

    /**
     * Mirrors AuthController::login()'s success payload so the Angular
     * client can treat passkey login identically to password login.
     */
    public function toResponse($request)
    {
        $user = User::find(Auth::id());

        $user->tokens()->delete();

        return $this->success([
            'user' => Auth::user(),
            'token' => $user->createToken('Auth token for ' . $user->name)->plainTextToken,
        ]);
    }
}
