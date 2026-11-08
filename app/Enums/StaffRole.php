<?php

namespace App\Enums;

enum StaffRole: string
{
    case Owner = 'owner';
    case Support = 'support';
    case Viewer = 'viewer';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
