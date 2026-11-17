<?php

namespace App\Exceptions;

use App\Enums\InvitationStatus;
use RuntimeException;

/**
 * Thrown when an invitation is used after it was already accepted,
 * cancelled or has expired.
 */
class InvitationUnavailableException extends RuntimeException
{
    public function __construct(public readonly InvitationStatus $status)
    {
        parent::__construct('This invitation can no longer be used.');
    }
}
