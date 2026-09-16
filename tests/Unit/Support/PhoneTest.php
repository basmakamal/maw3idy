<?php

use App\Support\Phone;

it('normalises the ways customers actually type numbers', function (string $raw, string $expected) {
    expect(Phone::normalize($raw))->toBe($expected);
})->with([
    'national with spaces' => ['050 123 4567', '0501234567'],
    'international with plus and spaces' => ['+966 50 123 4567', '+966501234567'],
    'international with 00 prefix' => ['00966501234567', '+966501234567'],
    'dashes and brackets' => ['(050) 123-4567', '0501234567'],
    'arabic-indic digits' => ['٠٥٠١٢٣٤٥٦٧', '0501234567'],
    'extended arabic-indic digits' => ['+۹۶۶۵۰۱۲۳۴۵۶۷', '+966501234567'],
    'surrounding whitespace' => ["  0501234567\n", '0501234567'],
]);

it('accepts 8 to 15 digits with an optional plus', function (string $number, bool $valid) {
    expect(Phone::isValid($number))->toBe($valid);
})->with([
    ['0501234567', true],
    ['+966501234567', true],
    ['12345678', true],
    ['1234567', false],
    ['+1234567890123456', false],
    ['05012345a7', false],
    ['', false],
]);
