<?php

declare(strict_types=1);

namespace Src\Shared\Domain\Exceptions;

/** An external service the operation needs does not answer; the caller can try again later. */
abstract class ServiceUnavailableException extends DomainException {}
