<?php

declare(strict_types=1);

use Iak\Result\Failure;
use Iak\Result\Result;
use Iak\Result\Success;
use Iak\Result\Tests\Fixtures\TestError;

it('map transforms the success value', function () {
    $result = Result::success(21)->map(fn (int $value) => $value * 2);

    expect($result)->toBeInstanceOf(Success::class)
        ->and($result->value())->toBe(42);
});

it('map leaves a failure untouched and does not invoke the callback', function () {
    $called = false;

    $result = Result::failure('nope')->map(function () use (&$called) {
        $called = true;

        return 1;
    });

    expect($result)->toBeInstanceOf(Failure::class)
        ->and($result->error())->toBe('nope')
        ->and($called)->toBeFalse();
});

it('mapError transforms the error value', function () {
    $result = Result::failure(TestError::CardExpired)->map(fn () => 1)
        ->mapError(fn (TestError $error) => $error->name);

    expect($result->error())->toBe('CardExpired');
});

it('mapError leaves a success untouched and does not invoke the callback', function () {
    $called = false;

    $result = Result::success(42)->mapError(function () use (&$called) {
        $called = true;

        return 'nope';
    });

    expect($result)->toBeInstanceOf(Success::class)
        ->and($result->value())->toBe(42)
        ->and($called)->toBeFalse();
});

it('chain pipes the success value into the next result', function () {
    $result = Result::success(21)->chain(fn (int $value) => Result::success($value * 2));

    expect($result->value())->toBe(42);
});

it('chain returns the failure produced by the callback', function () {
    $result = Result::success(21)->chain(fn () => Result::failure('nope'));

    expect($result->isFailure())->toBeTrue()
        ->and($result->error())->toBe('nope');
});

it('chain short-circuits on failure without invoking the callback', function () {
    $called = false;

    $result = Result::failure('nope')->chain(function () use (&$called) {
        $called = true;

        return Result::success(1);
    });

    expect($result->isFailure())->toBeTrue()
        ->and($result->error())->toBe('nope')
        ->and($called)->toBeFalse();
});

it('orElse recovers a failure into the next result', function () {
    $result = Result::failure('nope')->orElse(fn (string $error) => Result::success(strlen($error)));

    expect($result->value())->toBe(4);
});

it('orElse can produce a new failure', function () {
    $result = Result::failure('nope')->orElse(fn () => Result::failure(TestError::CardExpired));

    expect($result->error())->toBe(TestError::CardExpired);
});

it('orElse passes a success through without invoking the callback', function () {
    $called = false;

    $result = Result::success(42)->orElse(function () use (&$called) {
        $called = true;

        return Result::failure('nope');
    });

    expect($result->value())->toBe(42)->and($called)->toBeFalse();
});
