<?php

declare(strict_types=1);

use Iak\Result\ResultException;
use Iak\Result\Tests\Fixtures\TestError;

it('carries the message and the contained value', function () {
    $exception = new ResultException('Something went wrong', 42);

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())->toBe('Something went wrong')
        ->and($exception->value)->toBe(42);
});

it('uses the caller message verbatim in withMessage', function () {
    $exception = ResultException::withMessage('Order must be placeable', 'nope');

    expect($exception->getMessage())->toBe('Order must be placeable')
        ->and($exception->value)->toBe('nope');
});

it('chains a throwable value as the previous exception', function () {
    $error = new LogicException('boom');

    $exception = ResultException::withMessage('Charge failed', $error);

    expect($exception->getPrevious())->toBe($error)
        ->and($exception->value)->toBe($error);
});

it('does not chain non-throwable values', function () {
    $exception = ResultException::withMessage('Charge failed', 'card_expired');

    expect($exception->getPrevious())->toBeNull();
});

it('describes enum values with class and case name', function () {
    $exception = ResultException::describing('Cannot get the value of a failure result', TestError::CardExpired);

    expect($exception->getMessage())->toBe(
        'Cannot get the value of a failure result (contained: Iak\Result\Tests\Fixtures\TestError::CardExpired).'
    );
});

it('describes scalar, object, array and null values', function (mixed $value, string $rendered) {
    $exception = ResultException::describing('Problem', $value);

    expect($exception->getMessage())->toBe("Problem (contained: {$rendered}).");
})->with([
    'string' => ['card_expired', '"card_expired"'],
    'int' => [42, '42'],
    'float' => [1.5, '1.5'],
    'bool' => [true, 'true'],
    'null' => [null, 'null'],
    'object' => [new stdClass, 'stdClass'],
    'array' => [[1, 2], 'array'],
]);
