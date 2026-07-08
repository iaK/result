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
     * Combine many results into one: a success holding every value (keys
     * preserved) when all succeed, or the first failure encountered.
     *
     * @template TKey of array-key
     * @template TVal
     * @template TErr
     *
     * @param  iterable<TKey, Result<TVal, TErr>>  $results
     * @return Result<array<TKey, TVal>, TErr>
     */
    public static function all(iterable $results): Result
    {
        $values = [];

        foreach ($results as $key => $result) {
            if ($result->isFailure()) {
                return $result;
            }

            $values[$key] = $result->value();
        }

        return new Success($values);
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

    /**
     * Transform the success value, leaving a failure untouched.
     *
     * @template U
     *
     * @param  callable(T): U  $fn
     * @return Result<U, E>
     */
    abstract public function map(callable $fn): Result;

    /**
     * Transform the error value, leaving a success untouched.
     *
     * @template F
     *
     * @param  callable(E): F  $fn
     * @return Result<T, F>
     */
    abstract public function mapError(callable $fn): Result;

    /**
     * Chain another result-returning operation on the success value; a
     * failure short-circuits past it. Error types accumulate as a union.
     *
     * @template U
     * @template F
     *
     * @param  callable(T): Result<U, F>  $fn
     * @return Result<U, E|F>
     */
    abstract public function chain(callable $fn): Result;

    /**
     * Recover from a failure with another result-returning operation; a
     * success passes through untouched.
     *
     * @template U
     * @template F
     *
     * @param  callable(E): Result<U, F>  $fn
     * @return Result<T|U, F>
     */
    abstract public function orElse(callable $fn): Result;

    /**
     * Run a side effect on the success value, returning the result untouched.
     *
     * @param  callable(T): void  $fn
     * @return $this
     */
    abstract public function tap(callable $fn): static;

    /**
     * Run a side effect on the error value, returning the result untouched.
     *
     * @param  callable(E): void  $fn
     * @return $this
     */
    abstract public function tapError(callable $fn): static;

    /**
     * Handle both variants and return the outcome of the matching arm.
     *
     * @template TSuccessReturn
     * @template TFailureReturn
     *
     * @param  callable(T): TSuccessReturn  $success
     * @param  callable(E): TFailureReturn  $failure
     * @return TSuccessReturn|TFailureReturn
     */
    abstract public function match(callable $success, callable $failure): mixed;
}
