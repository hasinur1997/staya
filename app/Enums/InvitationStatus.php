<?php

namespace App\Enums;

/**
 * Where an invitation stands. Derived from its timestamps, never stored:
 * used beats revoked beats expired.
 */
enum InvitationStatus: string
{
    case Pending = 'pending';
    case Used = 'used';
    case Revoked = 'revoked';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Used => __('Accepted'),
            self::Revoked => __('Cancelled'),
            self::Expired => __('Expired'),
        };
    }
}
