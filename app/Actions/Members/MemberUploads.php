<?php

namespace App\Actions\Members;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Member files on the private `uploads` disk. Files are stored under a
 * random name with the extension of their real (content-detected) type.
 * They are written before the database transaction, so a failed save must
 * call `discard()` to leave nothing behind.
 */
class MemberUploads
{
    public const string DISK = 'uploads';

    /** @var list<string> */
    private array $stored = [];

    /**
     * Store the file, if one was sent, and return its path.
     */
    public function store(?UploadedFile $file, string $folder): ?string
    {
        if ($file === null) {
            return null;
        }

        $path = $file->store('members/'.$folder, self::DISK);

        if ($path === false) {
            $this->discard();

            throw new \RuntimeException('The upload could not be stored.');
        }

        return $this->stored[] = $path;
    }

    /**
     * Delete every file this instance stored.
     */
    public function discard(): void
    {
        self::delete(...$this->stored);

        $this->stored = [];
    }

    /**
     * Delete stored files, ignoring empty paths.
     */
    public static function delete(?string ...$paths): void
    {
        $paths = array_values(array_filter($paths, fn (?string $path) => $path !== null && $path !== ''));

        if ($paths !== []) {
            Storage::disk(self::DISK)->delete($paths);
        }
    }
}
