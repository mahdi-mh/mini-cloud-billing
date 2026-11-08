<?php

namespace Modules\Billing\Enums;

enum BillableType: string
{
    case Server = 'server';
    case Ip = 'ip';
}
