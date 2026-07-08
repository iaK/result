<?php

declare(strict_types=1);

use Iak\Result\Result;
use Iak\Result\Success;
use Iak\Result\Tests\Fixtures\TestError;

it('combines all successes into one success holding every value', function () {
    $result = Result::all([
        Result::success(1),
        Result::success(2),
        Result::success(3),
    ]);

    expect($result)->toBeInstanceOf(Success::class)
        ->and($result->value())->toBe([1, 2, 3]);
});

it('preserves array keys', function () {
    $result = Result::all([
        'first' => Result::success(1),
        'second' => Result::success(2),
    ]);

    expect($result->value())->toBe(['first' => 1, 'second' => 2]);
});

it('returns the first failure encountered as-is', function () {
    $failure = Result::failure(TestError::CardExpired);

    $result = Result::all([
        Result::success(1),
        $failure,
        Result::failure(TestError::InsufficientFunds),
    ]);

    expect($result)->toBe($failure);
});

it('combines an empty iterable into an empty success', function () {
    $result = Result::all([]);

    expect($result)->toBeInstanceOf(Success::class)
        ->and($result->value())->toBe([]);
});

it('accepts any iterable and stops consuming it after the first failure', function () {
    $consumed = 0;

    $results = (function () use (&$consumed) {
        $consumed++;
        yield Result::success(1);
        $consumed++;
        yield Result::failure('nope');
        $consumed++;
        yield Result::success(3);
    })();

    $result = Result::all($results);

    expect($result->isFailure())->toBeTrue()
        ->and($result->error())->toBe('nope')
        ->and($consumed)->toBe(2);
});
