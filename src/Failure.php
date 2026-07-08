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

    public function expect(string $message): never
    {
        throw ResultException::withMessage($message, $this->error);
    }

    public function expectError(string $message): mixed
    {
        return $this->error;
    }

    public function valueOr(mixed $default): mixed
    {
        return $default;
    }

    public function valueOrElse(callable $fallback): mixed
    {
        return $fallback($this->error);
    }

    public function map(callable $fn): Result
    {
        return $this;
    }

    public function mapError(callable $fn): Result
    {
        return new Failure($fn($this->error));
    }

    public function chain(callable $fn): Result
    {
        return $this;
    }

    public function orElse(callable $fn): Result
    {
        return $fn($this->error);
    }

    public function tap(callable $fn): static
    {
        return $this;
    }

    public function tapError(callable $fn): static
    {
        $fn($this->error);

        return $this;
    }

    public function match(callable $success, callable $failure): mixed
    {
        return $failure($this->error);
    }
}
