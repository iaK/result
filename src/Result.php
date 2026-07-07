<?php

declare(strict_types=1);

namespace Iak\Result;

/**
 * A value that is either a {@see Success} holding a value of type T,
 * or a {@see Failure} holding an error of type E.
 *
 * @template-covariant T
 * @template-covariant E
 *
 * @immutable
 */
abstract class Result
{
    /**
     * Wrap a value in a success result. Call without arguments for a
     * result that carries no meaningful value.
     *
     * @template TVal = null
     *
     * @param  TVal  $value
     * @return Success<TVal>
     */
    public static function success(mixed $value = null): Success
    {
        return new Success($value);
    }

    /**
     * Wrap an error in a failure result.
     *
     * @template TErr
     *
     * @param  TErr  $error
     * @return Failure<TErr>
     */
    public static function failure(mixed $error): Failure
    {
        return new Failure($error);
    }

    /**
     * @phpstan-assert-if-true Success<T> $this
     *
     * @phpstan-assert-if-false Failure<E> $this
     */
    abstract public function isSuccess(): bool;

    /**
     * @phpstan-assert-if-true Failure<E> $this
     *
     * @phpstan-assert-if-false Success<T> $this
     */
    abstract public function isFailure(): bool;

    /**
     * The success value.
     *
     * @return T
     *
     * @throws ResultException on a failure result
     */
    abstract public function value(): mixed;

    /**
     * The error value.
     *
     * @return E
     *
     * @throws ResultException on a success result
     */
    abstract public function error(): mixed;

    /**
     * The success value, or throw with the caller's message on failure.
     *
     * @return T
     *
     * @throws ResultException on a failure result
     */
    abstract public function expect(string $message): mixed;

    /**
     * The error value, or throw with the caller's message on success.
     *
     * @return E
     *
     * @throws ResultException on a success result
     */
    abstract public function expectError(string $message): mixed;

    /**
     * The success value, or the given default on failure.
     *
     * @template TDefault
     *
     * @param  TDefault  $default
     * @return T|TDefault
     */
    abstract public function valueOr(mixed $default): mixed;

    /**
     * The success value, or the fallback's return value on failure.
     *
     * @template TDefault
     *
     * @param  callable(E): TDefault  $fallback
     * @return T|TDefault
     */
    abstract public function valueOrElse(callable $fallback): mixed;
}
