<?php

declare(strict_types=1);

namespace Iak\Result;

/**
 * @template-covariant T
 *
 * @extends Result<T, never>
 *
 * @immutable
 */
final class Success extends Result
{
    /**
     * @param  T  $value
     */
    public function __construct(
        private readonly mixed $value,
    ) {}

    public function isSuccess(): bool
    {
        return true;
    }

    public function isFailure(): bool
    {
        return false;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function error(): never
    {
        throw ResultException::describing('Cannot get the error of a success result', $this->value);
    }
}
