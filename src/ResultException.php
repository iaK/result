<?php

declare(strict_types=1);

namespace Iak\Result;

use RuntimeException;
use Throwable;
use UnitEnum;

/**
 * Thrown when a result's payload is read from the wrong variant, e.g.
 * value() on a failure. Carries the offending contained value, and chains
 * it as the previous exception when it happens to be a Throwable.
 */
final class ResultException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly mixed $value = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Build an exception whose message is authored by the caller,
     * as in expect() and expectError().
     */
    public static function withMessage(string $message, mixed $value): self
    {
        return new self($message, $value, $value instanceof Throwable ? $value : null);
    }

    /**
     * Build an exception for a wrong-variant access, appending a short
     * rendering of the contained value to the problem description.
     */
    public static function describing(string $problem, mixed $value): self
    {
        return self::withMessage(sprintf('%s (contained: %s).', $problem, self::render($value)), $value);
    }

    private static function render(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value instanceof UnitEnum => $value::class.'::'.$value->name,
            is_object($value) => $value::class,
            is_string($value) => '"'.$value.'"',
            is_scalar($value) => var_export($value, true),
            default => get_debug_type($value),
        };
    }
}
