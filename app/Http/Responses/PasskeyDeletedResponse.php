<?php

namespace App\Http\Responses;

use App\Traits\HttpResponses;
use Laravel\Passkeys\Contracts\PasskeyDeletedResponse as PasskeyDeletedResponseContract;

class PasskeyDeletedResponse implements PasskeyDeletedResponseContract
{
    use HttpResponses;

    public function toResponse($request)
    {
        return $this->success([], 'Passkey removed.');
    }
}
