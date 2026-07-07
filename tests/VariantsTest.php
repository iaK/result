<?php

declare(strict_types=1);

use Iak\Result\Failure;
use Iak\Result\Result;
use Iak\Result\ResultException;
use Iak\Result\Success;
use Iak\Result\Tests\Fixtures\TestError;

it('creates a success result holding a value', function () {
    $result = Result::success(42);

    expect($result)->toBeInstanceOf(Success::class)
        ->and($result->isSuccess())->toBeTrue()
        ->and($result->isFailure())->toBeFalse()
        ->and($result->value())->toBe(42);
});

it('creates a unit success result when no value is given', function () {
    expect(Result::success()->value())->toBeNull();
});

it('creates a failure result holding an error', function () {
    $result = Result::failure(TestError::CardExpired);

    expect($result)->toBeInstanceOf(Failure::class)
        ->and($result->isFailure())->toBeTrue()
        ->and($result->isSuccess())->toBeFalse()
        ->and($result->error())->toBe(TestError::CardExpired);
});

it('can be constructed directly', function () {
    expect(new Success(1))->toEqual(Result::success(1))
        ->and(new Failure('nope'))->toEqual(Result::failure('nope'));
});

it('throws when reading the value of a failure', function () {
    $failure = Result::failure(TestError::CardExpired);

    expect(fn () => $failure->value())
        ->toThrow(ResultException::class, 'Cannot get the value of a failure result');
});

it('exposes the error on the exception when reading the value of a failure', function () {
    try {
        Result::failure(TestError::CardExpired)->value();
        $this->fail('Expected a ResultException.');
    } catch (ResultException $exception) {
        expect($exception->value)->toBe(TestError::CardExpired);
    }
});

it('chains the previous exception when the error is a throwable', function () {
    $error = new LogicException('boom');

    try {
        Result::failure($error)->value();
        $this->fail('Expected a ResultException.');
    } catch (ResultException $exception) {
        expect($exception->getPrevious())->toBe($error);
    }
});

it('throws when reading the error of a success', function () {
    expect(fn () => Result::success(42)->error())
        ->toThrow(ResultException::class, 'Cannot get the error of a success result');
});
