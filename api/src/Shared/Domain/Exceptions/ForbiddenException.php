<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

/** The account is known, but what it asks is not allowed right now; the API answers 403. */
abstract class ForbiddenException extends DomainException {}
