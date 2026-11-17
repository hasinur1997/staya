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
}
