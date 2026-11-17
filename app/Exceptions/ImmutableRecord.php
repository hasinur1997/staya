<?php

namespace App\Exceptions;

use LogicException;

/**
 * Thrown when code tries to change fields of a financial record that are
 * fixed once it exists, or to delete the record.
 */
class ImmutableRecord extends LogicException
{
    /**
     * @param  list<string>  $fields
     */
    public static function fields(string $table, array $fields): self
    {
        return new self("[{$table}] fields [".implode(', ', $fields).'] cannot change after insert.');
    }

    public static function deletion(string $table): self
    {
        return new self("Rows in [{$table}] are never deleted.");
    }
}
