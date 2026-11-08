<?php

namespace Modules\Wallet\Support;

use Illuminate\Support\Number;

class Money
{
    /** Money is stored as integer cents; this is only for showing it. */
    public static function format(int $minor): string
    {
        return Number::currency($minor / 100, 'USD');
    }

    /** "12", "12.5" or "12.50" to 1250. Anything else is null: no floats on the way. */
    public static function parse(string $input): ?int
    {
        if (! preg_match('/^(\d{1,9})(?:\.(\d{1,2}))?$/', trim($input), $m)) {
            return null;
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }
}
