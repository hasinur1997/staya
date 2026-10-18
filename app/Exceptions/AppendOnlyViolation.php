<?php

namespace App\Exceptions;

use LogicException;

/**
 * Thrown when code tries to change or remove a row from an append-only table.
 */
class AppendOnlyViolation extends LogicException
{
    public static function for(string $table): self
    {
        return new self("Rows in [{$table}] are append-only and cannot be updated or deleted.");
    }
}
