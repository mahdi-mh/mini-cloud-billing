<?php

namespace Modules\Wallet\Enums;

enum LedgerEntryType: string
{
    case TopUp = 'topup';
    case Charge = 'charge';
    case Refund = 'refund';
}
