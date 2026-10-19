<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Contracts;

interface ImageStorage
{
    /** Store an image in a folder under a generated name and answer its stored path. */
    public function store(string $directory, string $sourcePath, string $extension): string;

    /** Remove a stored image; a missing one is ignored. */
    public function delete(string $path): void;
}
