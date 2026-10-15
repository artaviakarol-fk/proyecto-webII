<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Identity\Users\Application\DTOs\IssuedToken;

/**
 * @property-read IssuedToken $resource
 */
final class TokenResource extends JsonResource
{
    private const string TYPE = 'Bearer';

    /** Wrap a token issued at login. */
    public function __construct(private readonly IssuedToken $token)
    {
        parent::__construct($token);
    }

    /**
     * Expose the token, the way to send it and what it allows.
     *
     * @return array<string, string|list<string>>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token->plainTextToken,
            'tipo' => self::TYPE,
            'abilities' => $this->token->abilities,
        ];
    }
}
