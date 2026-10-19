<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Application\UseCases;

use Src\Negocios\Profile\Domain\Contracts\NegocioRepository;
use Src\Negocios\Profile\Domain\Entities\Negocio;
use Src\Shared\Application\DTOs\ImageUpload;
use Src\Shared\Domain\Contracts\ImageStorage;
use Src\Shared\Domain\ImageFolders;

final readonly class ChangeNegocioLogo
{
    /** Receive the use case that finds the current negocio, its repository and the image storage. */
    public function __construct(
        private GetCurrentNegocio $getCurrentNegocio,
        private NegocioRepository $negocios,
        private ImageStorage $images,
    ) {}

    /** Store the logo in the folder of the negocio and remove the one it replaces. */
    public function execute(ImageUpload $image): Negocio
    {
        $negocio = $this->getCurrentNegocio->execute();

        $path = $this->images->store(ImageFolders::logo($negocio->id), $image->sourcePath, $image->extension);
        $replaced = $negocio->changeLogo($path);
        $this->negocios->save($negocio);

        if ($replaced !== null) {
            $this->images->delete($replaced);
        }

        return $negocio;
    }
}
