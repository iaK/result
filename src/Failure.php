<?php

declare(strict_types=1);

namespace Iak\Result;

/**
 * @template-covariant E
 *
 * @extends Result<never, E>
 *
 * @immutable
 */
final class Failure extends Result
{
    /**
     * @param  E  $error
     */
    public function __construct(
        private readonly mixed $error,
    ) {}

    public function isSuccess(): bool
    {
        return false;
    }

    public function isFailure(): bool
    {
        return true;
    }

    public function value(): never
    {
        throw ResultException::describing('Cannot get the value of a failure result', $this->error);
    }

    public function error(): mixed
    {
        return $this->error;
    }
}
