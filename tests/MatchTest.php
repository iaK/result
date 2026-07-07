<?php

declare(strict_types=1);

use Iak\Result\Result;
use Iak\Result\Tests\Fixtures\TestError;

it('match runs the success arm with the value', function () {
    $outcome = Result::success(42)->match(
        success: fn (int $value) => "value: {$value}",
        failure: fn () => 'failed',
    );

    expect($outcome)->toBe('value: 42');
});

it('match runs the failure arm with the error', function () {
    $outcome = Result::failure(TestError::CardExpired)->match(
        success: fn () => 'ok',
        failure: fn (TestError $error) => "error: {$error->name}",
    );

    expect($outcome)->toBe('error: CardExpired');
});

it('match only invokes the relevant arm', function () {
    $successCalls = 0;
    $failureCalls = 0;

    Result::success(1)->match(
        success: function () use (&$successCalls) {
            return $successCalls++;
        },
        failure: function () use (&$failureCalls) {
            return $failureCalls++;
        },
    );

    expect($successCalls)->toBe(1)->and($failureCalls)->toBe(0);
});
