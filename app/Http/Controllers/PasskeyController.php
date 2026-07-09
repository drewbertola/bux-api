<?php

namespace App\Http\Controllers;

use App\Traits\HttpResponses;
use Illuminate\Support\Facades\Auth;

class PasskeyController extends Controller
{
    use HttpResponses;

    public function index()
    {
        return $this->success([
            'passkeys' => Auth::user()->passkeys,
        ]);
    }
}
