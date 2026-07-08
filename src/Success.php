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

    public function expect(string $message): mixed
    {
        return $this->value;
    }

    public function expectError(string $message): never
    {
        throw ResultException::withMessage($message, $this->value);
    }

    public function valueOr(mixed $default): mixed
    {
        return $this->value;
    }

    public function valueOrElse(callable $fallback): mixed
    {
        return $this->value;
    }

    public function map(callable $fn): Result
    {
        return new Success($fn($this->value));
    }

    public function mapError(callable $fn): Result
    {
        return $this;
    }

    public function chain(callable $fn): Result
    {
        return $fn($this->value);
    }

    public function orElse(callable $fn): Result
    {
        return $this;
    }

    public function tap(callable $fn): static
    {
        $fn($this->value);

        return $this;
    }

    public function tapError(callable $fn): static
    {
        return $this;
    }

    public function match(callable $success, callable $failure): mixed
    {
        return $success($this->value);
    }
}
