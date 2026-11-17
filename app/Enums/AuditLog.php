<?php

namespace App\Enums;

/**
 * Activity log names (`activity_log.log_name`).
 */
enum AuditLog: string
{
    case Auth = 'auth';
    case Access = 'access';
    case Settings = 'settings';
    case Ledger = 'ledger';
    case Member = 'member';
    case Deposit = 'deposit';
    case Grocery = 'grocery';
    case Report = 'report';

    public function label(): string
    {
        return match ($this) {
            self::Auth => __('Sign-ins'),
            self::Access => __('Users and roles'),
            self::Settings => __('Settings'),
            self::Ledger => __('Ledger'),
            self::Member => __('Members'),
            self::Deposit => __('Deposits'),
            self::Grocery => __('Grocery purchases'),
            self::Report => __('Reports'),
        };
    }
}
