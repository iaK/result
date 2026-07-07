<?php

declare(strict_types=1);

namespace Iak\Result\Types;

use Iak\Result\Failure;
use Iak\Result\Result;
use Iak\Result\Success;

use function PHPStan\Testing\assertType;

enum PaymentError
{
    case CardExpired;
    case InsufficientFunds;
}

final class Receipt {}

/**
 * @return Result<Receipt, PaymentError>
 */
function charge(bool $succeeds): Result
{
    return $succeeds
        ? Result::success(new Receipt)
        : Result::failure(PaymentError::CardExpired);
}

/**
 * @return Result<int, string>
 */
function nextStep(Receipt $receipt): Result
{
    return Result::success(1);
}

/**
 * @param  Result<Receipt, PaymentError>  $result
 */
function acceptsResult(Result $result): void {}

function constructors(): void
{
    assertType('Iak\Result\Success<int>', Result::success(1));
    assertType('Iak\Result\Success<null>', Result::success());
    assertType(
        'Iak\Result\Failure<Iak\Result\Types\PaymentError::CardExpired>',
        Result::failure(PaymentError::CardExpired),
    );
}

function narrowing(bool $flag): void
{
    $result = charge($flag);

    assertType('Iak\Result\Result<Iak\Result\Types\Receipt, Iak\Result\Types\PaymentError>', $result);

    if ($result->isSuccess()) {
        assertType('Iak\Result\Success<Iak\Result\Types\Receipt>', $result);
        assertType('Iak\Result\Types\Receipt', $result->value());
    } else {
        assertType('Iak\Result\Failure<Iak\Result\Types\PaymentError>', $result);
        assertType('Iak\Result\Types\PaymentError', $result->error());
    }

    $again = charge($flag);

    if ($again->isFailure()) {
        assertType('Iak\Result\Types\PaymentError', $again->error());
    } else {
        assertType('Iak\Result\Types\Receipt', $again->value());
    }

    // NOTE: bare `instanceof Success` narrows only the class, not the
    // payload generics (PHPStan engine limitation, verified on 2.2.5).
    // Use isSuccess()/isFailure(), whose @phpstan-assert tags carry T
    // and E through — asserted above.
}

function combinators(bool $flag): void
{
    $result = charge($flag);

    assertType(
        'Iak\Result\Result<int, Iak\Result\Types\PaymentError>',
        $result->map(fn (Receipt $receipt): int => 1),
    );

    assertType(
        'Iak\Result\Result<Iak\Result\Types\Receipt, string>',
        $result->mapError(fn (PaymentError $error): string => $error->name),
    );

    assertType(
        'Iak\Result\Result<int, Iak\Result\Types\PaymentError|string>',
        $result->chain(nextStep(...)),
    );

    assertType(
        'Iak\Result\Result<Iak\Result\Types\Receipt|int, string>',
        $result->orElse(fn (PaymentError $error): Result => nextStep(new Receipt)),
    );

    assertType('1|Iak\Result\Types\Receipt', $result->valueOr(1));

    assertType(
        "'CardExpired'|'InsufficientFunds'|Iak\Result\Types\Receipt",
        $result->valueOrElse(fn (PaymentError $error): string => $error->name),
    );

    assertType(
        "1|'CardExpired'|'InsufficientFunds'",
        $result->match(
            success: fn (Receipt $receipt): int => 1,
            failure: fn (PaymentError $error): string => $error->name,
        ),
    );
}

function covariance(): void
{
    acceptsResult(Result::success(new Receipt));
    acceptsResult(Result::failure(PaymentError::CardExpired));
    acceptsResult(new Success(new Receipt));
    acceptsResult(new Failure(PaymentError::InsufficientFunds));
}

function neverOnWrongVariant(): void
{
    $failure = Result::failure(PaymentError::CardExpired);

    assertType('never', $failure->value());
}

function neverOnSuccessSide(): void
{
    $success = Result::success(new Receipt);

    assertType('never', $success->error());
}
