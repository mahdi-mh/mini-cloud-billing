<?php

namespace Modules\Customers\Enums;

enum CustomerStatusType: string
{
    case Verified = 'verified';
    case Suspended = 'suspended';

    public function color(): string
    {
        return match ($this) {
            self::Verified => 'green',
            self::Suspended => 'red',
        };
    }
}
