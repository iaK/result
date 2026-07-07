<?php

declare(strict_types=1);

use Iak\Result\Result;
use Iak\Result\Tests\Fixtures\TestError;

it('compares results structurally with ==', function () {
    expect(Result::success(1) == Result::success(1))->toBeTrue()
        ->and(Result::success(1) == Result::success(2))->toBeFalse()
        ->and(Result::failure('a') == Result::failure('a'))->toBeTrue()
        ->and(Result::success(1) == Result::failure(1))->toBeFalse();
});

it('round-trips through serialization', function () {
    $success = Result::success([1, 2, 3]);
    $failure = Result::failure(TestError::CardExpired);

    expect(unserialize(serialize($success)) == $success)->toBeTrue()
        ->and(unserialize(serialize($failure)) == $failure)->toBeTrue();
});
