<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Infrastructure\Http\Controllers;

use Src\Negocios\Profile\Application\UseCases\ChangeNegocioLogo;
use Src\Negocios\Profile\Application\UseCases\GetCurrentNegocio;
use Src\Negocios\Profile\Domain\Exceptions\LogoNotFoundException;
use Src\Negocios\Profile\Infrastructure\Http\Resources\NegocioResource;
use Src\Shared\Infrastructure\Http\Requests\UploadImageRequest;
use Src\Shared\Infrastructure\Storage\DiskImageStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class NegocioLogoController
{
    /** Upload or replace the logo of the negocio of the account (multipart field "imagen"). */
    public function store(UploadImageRequest $request, ChangeNegocioLogo $changeLogo): NegocioResource
    {
        return new NegocioResource($changeLogo->execute($request->toUpload()));
    }

    /** Send the logo of the negocio of the account. */
    public function show(GetCurrentNegocio $getCurrentNegocio): StreamedResponse
    {
        $path = $getCurrentNegocio->execute()->logoPath ?? throw LogoNotFoundException::create();

        return DiskImageStorage::disk()->response($path);
    }
}
