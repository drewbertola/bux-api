<?php

namespace App\Http\Middleware;

use App\Traits\HttpResponses;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class RequireCurrentPassword
{
    use HttpResponses;

    /**
     * Block requests to sensitive account-management endpoints (e.g.
     * registering a new passkey) unless the caller proves knowledge of
     * the account's current password. Without this, a hijacked session
     * or stolen token could plant a persistent login method that
     * survives a token revocation or password change.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $password = $request->input('password');

        if (empty($user) || empty($password) || !Hash::check($password, $user->password)) {
            return $this->error([
                'errors' => ['password' => ['The current password is incorrect.']],
            ], 'One or more errors were encountered.');
        }

        return $next($request);
    }
}
