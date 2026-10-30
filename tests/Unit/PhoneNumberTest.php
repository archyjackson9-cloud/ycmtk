<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function numbers(): array
    {
        return [
            'local format' => ['0244123456', '233244123456'],
            'plus international' => ['+233244123456', '233244123456'],
            'international' => ['233244123456', '233244123456'],
            'double zero prefix' => ['00233244123456', '233244123456'],
            'spaces and dashes' => ['024 412-3456', '233244123456'],
            'plus with separators' => ['+233 (24) 412 3456', '233244123456'],
            'no leading zero' => ['244123456', '233244123456'],
            '5x network' => ['0551234567', '233551234567'],
            'null' => [null, null],
            'empty' => ['', null],
            'letters only' => ['abc', null],
            'too short' => ['024412345', null],
            'too long' => ['02441234567', null],
            'foreign number' => ['+447911123456', null],
            'landline-looking' => ['0312345678', null],
        ];
    }

    #[DataProvider('numbers')]
    public function test_it_normalises_to_arkesel_format(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::toArkesel($input));
        $this->assertSame($expected !== null, PhoneNumber::isValid($input));
    }
}
