<?php

namespace App\Enums;

enum TenantStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    /**
     * Whether members of a tenant in this status may use the application.
     */
    public function allowsAccess(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::PastDue], true);
    }
}
