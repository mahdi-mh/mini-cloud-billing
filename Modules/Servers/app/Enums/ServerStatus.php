<?php

namespace Modules\Servers\Enums;

enum ServerStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Terminating = 'terminating';
    case Terminated = 'terminated';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Running => 'green',
            self::Terminating => 'orange',
            self::Terminated => 'zinc',
            self::Failed => 'red',
        };
    }

    /** Can this server still hold an IP address? */
    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Running], true);
    }
}
