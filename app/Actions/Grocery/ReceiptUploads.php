<?php

namespace App\Actions\Grocery;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Receipt photos on the private `uploads` disk, stored under a random name.
 * A receipt is written before the database transaction, so a failed save
 * must call `delete()` on the new path to leave nothing behind.
 */
class ReceiptUploads
{
    public const string DISK = 'uploads';

    /**
     * Store the file, if one was sent, and return its path.
     */
    public function store(?UploadedFile $file): ?string
    {
        if ($file === null) {
            return null;
        }

        $path = $file->store('grocery/receipts', self::DISK);

        if ($path === false) {
            throw new RuntimeException('The receipt could not be stored.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
