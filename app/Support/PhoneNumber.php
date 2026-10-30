<?php

namespace App\Support;

/**
 * Ghana phone number normalisation for the SMS gateway (Arkesel wants
 * international format without "+", e.g. 233244123456).
 */
class PhoneNumber
{
    /**
     * Accepts 0244123456, +233244123456, 233244123456, 00233244123456,
     * 244123456 (any spaces/dashes/brackets). Returns null when the value is
     * missing or is not a Ghana mobile number.
     */
    public static function toArkesel(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '00233')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '233')) {
            $national = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $national = substr($digits, 1);
        } else {
            $national = $digits;
        }

        // Ghana mobiles: 9 digits after the country code, starting 2x or 5x.
        if (! preg_match('/^[25]\d{8}$/', $national)) {
            return null;
        }

        return '233'.$national;
    }

    public static function isValid(?string $phone): bool
    {
        return self::toArkesel($phone) !== null;
    }
}
