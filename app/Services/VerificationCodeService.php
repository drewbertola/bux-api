<?php

namespace App\Services;

class VerificationCodeService {
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public static function generate()
    {
        $code = '';

        for ($i = 0; $i < 8; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
