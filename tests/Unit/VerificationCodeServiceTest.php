<?php

use App\Services\VerificationCodeService;

test('generated codes are 8 alphabetic characters', function () {
    $code = VerificationCodeService::generate();

    expect($code)->toHaveLength(8);
    expect($code)->toMatch('/^[a-zA-Z]{8}$/');
});

test('consecutive codes are not identical', function () {
    $first = VerificationCodeService::generate();
    $second = VerificationCodeService::generate();

    expect($first)->not->toBe($second);
});
