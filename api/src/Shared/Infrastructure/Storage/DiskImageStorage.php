<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Src\Shared\Domain\Contracts\ImageStorage;

/**
 * Images live on the private "local" disk (storage/app/private), never under public/.
 * They are served only through authenticated routes, so one negocio cannot open the files of another.
 */
final readonly class DiskImageStorage implements ImageStorage
{
    public const string DISK = 'local';

    /** Store the image under the hash of its contents, never under the name the person chose. */
    public function store(string $directory, string $sourcePath, string $extension): string
    {
        $name = hash_file('sha256', $sourcePath).'.'.$extension;

        return (string) self::disk()->putFileAs($directory, new File($sourcePath), $name);
    }

    /** Remove a stored image; a missing one is ignored. */
    public function delete(string $path): void
    {
        self::disk()->delete($path);
    }

    /** The disk that holds the images. */
    public static function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
