<?php

declare(strict_types=1);

use Iak\Result\Result;
use Iak\Result\ResultException;
use Iak\Result\Tests\Fixtures\TestError;

it('expect returns the value on success', function () {
    expect(Result::success(42)->expect('must have a value'))->toBe(42);
});

it('expect throws with the caller message on failure', function () {
    expect(fn () => Result::failure('nope')->expect('Order must be placeable'))
        ->toThrow(ResultException::class, 'Order must be placeable');
});

it('expectError returns the error on failure', function () {
    expect(Result::failure('nope')->expectError('must have failed'))->toBe('nope');
});

it('expectError throws with the caller message on success', function () {
    expect(fn () => Result::success(42)->expectError('must have failed'))
        ->toThrow(ResultException::class, 'must have failed');
});

it('valueOr returns the value on success', function () {
    expect(Result::success(42)->valueOr(0))->toBe(42);
});

it('valueOr returns the default on failure', function () {
    expect(Result::failure('nope')->valueOr(0))->toBe(0);
});

it('valueOrElse computes the fallback from the error on failure', function () {
    $value = Result::failure(TestError::CardExpired)
        ->valueOrElse(fn (TestError $error) => $error->name);

    expect($value)->toBe('CardExpired');
});

it('valueOrElse does not invoke the fallback on success', function () {
    $called = false;

    $value = Result::success(42)->valueOrElse(function () use (&$called) {
        $called = true;

        return 0;
    });

    expect($value)->toBe(42)->and($called)->toBeFalse();
});
