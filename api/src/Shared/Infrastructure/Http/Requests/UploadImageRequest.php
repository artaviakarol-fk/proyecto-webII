<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Src\Shared\Application\DTOs\ImageUpload;

/** An image sent as multipart/form-data in the field "imagen": JPG, PNG or WebP up to 2 MB. */
final class UploadImageRequest extends FormRequest
{
    public const int MAX_KILOBYTES = 2048;

    /**
     * Validation rules for the image; mimes checks the real content, not only the extension.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'imagen' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * Messages that explain the accepted types and the size limit.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'imagen.image' => __('shared.image_type'),
            'imagen.mimes' => __('shared.image_type'),
            'imagen.max' => __('shared.image_too_large', ['max' => self::MAX_KILOBYTES / 1024]),
        ];
    }

    /** Map the validated file to the use case input, with the extension guessed from its content. */
    public function toUpload(): ImageUpload
    {
        /** @var UploadedFile $file */
        $file = $this->file('imagen');
        $extension = $file->guessExtension() ?? 'jpg';

        return new ImageUpload((string) $file->getRealPath(), $extension === 'jpeg' ? 'jpg' : $extension);
    }
}
