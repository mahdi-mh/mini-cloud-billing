<?php

namespace Modules\Wallet\Support;

class Money
{
    /** Money is stored as integer cents; this is only for showing it. No floats on the way. */
    public static function format(int $minor): string
    {
        $abs = abs($minor);

        return ($minor < 0 ? '-' : '').'$'.number_format(intdiv($abs, 100)).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** "12", "12.5" or "12.50" to 1250. Anything else is null. */
    public static function parse(string $input): ?int
    {
        if (! preg_match('/^(\d{1,9})(?:\.(\d{1,2}))?$/', trim($input), $m)) {
            return null;
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }
}
