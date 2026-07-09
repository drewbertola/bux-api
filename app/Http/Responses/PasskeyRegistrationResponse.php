<?php

namespace App\Http\Responses;

use App\Traits\HttpResponses;
use Laravel\Passkeys\Contracts\PasskeyRegistrationResponse as PasskeyRegistrationResponseContract;
use Laravel\Passkeys\Passkey;

class PasskeyRegistrationResponse implements PasskeyRegistrationResponseContract
{
    use HttpResponses;

    protected ?Passkey $passkey = null;

    public function withPasskey(Passkey $passkey): static
    {
        $this->passkey = $passkey;

        return $this;
    }

    public function toResponse($request)
    {
        return $this->success([
            'passkey' => $this->passkey,
        ], 'Passkey added.');
    }
}
