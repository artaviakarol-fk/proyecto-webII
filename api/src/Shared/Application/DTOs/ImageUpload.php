<?php

declare(strict_types=1);

namespace Src\Shared\Application\DTOs;

final readonly class ImageUpload
{
    /** Carry an image already validated by the request: where it is now and its real extension. */
    public function __construct(
        public string $sourcePath,
        public string $extension,
    ) {}
}
