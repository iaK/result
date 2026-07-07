# Result

A fully typed Result object for PHP — Rust-inspired semantics with PHP-natural naming.

Failures become values instead of exceptions: a function that can fail returns
`Result<T, E>`, and PHPStan (level 9, generics, no extension needed) forces every
caller to handle both outcomes. Errors can be anything — enums, value objects,
strings, or exceptions.

```php
use Iak\Result\Result;

/** @return Result<Order, OrderError> */
public function handle(Cart $cart): Result
{
    if (! $cart->allItemsAvailable()) {
        return Result::failure(OrderError::OutOfStock);
    }

    return Result::success(Order::create($cart));
}

$order = $this->handle($cart)->match(
    success: fn (Order $order) => $order,
    failure: fn (OrderError $error) => abort(422, $error->message()),
);
```

## Installation

```bash
composer require iak/result
```

Requires PHP 8.2+. No other dependencies — works in any PHP project, Laravel or not.

## Creating results

```php
$success = Result::success($order);          // Success<Order>
$failure = Result::failure(OrderError::OutOfStock); // Failure<OrderError>
$unit    = Result::success();                // Success<null> — "nothing useful to return"
```

## Inspecting

`isSuccess()`/`isFailure()` narrow the type — after the check, PHPStan knows which
variant you hold, and the extractors are provably safe:

```php
if ($result->isFailure()) {
    Log::info('rejected', ['reason' => $result->error()->name]);

    return back()->withErrors(['order' => $result->error()->message()]);
}

$order = $result->value(); // Success<Order> here — cannot throw
```

Narrow with `isSuccess()`/`isFailure()`. A bare `instanceof` identifies the variant,
but PHPStan cannot carry the payload type through it (engine limitation) — so
`$result->error()` after `instanceof Failure` types as `mixed`, while after
`isFailure()` it types as `E` exactly.

## Extracting

```php
$result->value();                       // T — throws ResultException on a failure
$result->error();                       // E — throws ResultException on a success
$result->valueOr($default);             // T|TDefault
$result->valueOrElse(fn ($error) => …); // T|TDefault — lazy, receives the error
$result->expect('must exist');          // T — throws with YOUR message on a failure
$result->expectError('must fail');      // E — throws with YOUR message on a success
```

An unguarded `value()` call is the escape hatch — it throws `ResultException`
(carrying the error on `->value`, and chaining it as `->getPrevious()` when the
error is a `Throwable`). Reserve it for cases you are asserting cannot fail.

## Transforming and chaining

```php
$result->map(fn (Order $order) => $order->total);        // Result<Money, E>
$result->mapError(fn (GatewayError $e) => PaymentError::fromGateway($e));

// chain() pipes the success value into the next fallible step; the first
// failure short-circuits the rest. Error types accumulate as a union:
CreateOrder::make()->handle($cart)                                  // Result<Order, ValidationError>
    ->chain(fn (Order $o) => ChargeCustomer::make()->handle($o))    // Result<Receipt, ValidationError|PaymentError>
    ->orElse(fn ($error) => RetryPayment::make()->handle($cart))    // recover a failure
    ->match(
        success: fn (Receipt $receipt) => response()->json($receipt),
        failure: fn (ValidationError|PaymentError $e) => response()->json([], 422),
    );
```

> **Why `chain()` and not `then()`?** Promise libraries (Guzzle — and therefore
> Laravel's `Http::async()`/`Http::pool()` — and ReactPHP) treat any object with a
> public `then()` method as a promise and try to resolve it. A Result named that way
> would hang or crash promise pipelines.

## Use with [iak/action](https://github.com/iaK/action)

Actions that can fail return a `Result` from `handle()` — the signature documents
every possible outcome and PHPStan holds you to it:

```php
use Iak\Action\Action;
use Iak\Result\Result;

class ChargeCustomer extends Action
{
    /** @return Result<Receipt, PaymentError> */
    public function handle(Order $order): Result
    {
        if ($order->cardExpired()) {
            return Result::failure(PaymentError::CardExpired);
        }

        return Result::success($this->gateway->charge($order));
    }
}
```

Testing needs no extra wiring — `ChargeCustomer::test()->handle($order)` mirrors
`handle()` and is typed `Result<Receipt, PaymentError>` automatically.

**Idempotency caveat:** for `->idempotent($key)`, a returned `Failure` is a
*successful* run — the key is consumed and the `Failure` is cached and replayed on
subsequent calls. If you want a failed outcome to be retryable, forget the key on the
failure branch:

```php
$result = ChargeCustomer::make()
    ->idempotent("charge:{$order->id}")
    ->run(fn (ChargeCustomer $action) => $action->handle($order));

if ($result->isFailure()) {
    ChargeCustomer::make()->forgetIdempotency("charge:{$order->id}");
}
```

(Results serialize cleanly as long as the contained value does, so persistent cache
stores work.)

## Semantics worth knowing

- **Immutable** — transformations never mutate; they always return a result, never modify one in place.
- **Structural equality** — `Result::success(1) == Result::success(1)` is `true`.
- **Sealed** — `Success` and `Failure` are final; `isSuccess() === false` always
  means `Failure`.

## Development

```bash
composer test      # Pest
composer analyse   # PHPStan level 9, including type-inference fixtures in types/
composer format    # Pint
```

## License

MIT. See [LICENSE.md](LICENSE.md).
