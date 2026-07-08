# Result

A return type for operations that can fail.

```php
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

That one signature tells the whole story: this returns a `Receipt`, and the only
way it fails is a `PaymentError`. The caller decides what both outcomes mean:

```php
return $charge->handle($order)->match(
    success: fn (Receipt $receipt) => view('receipt', ['receipt' => $receipt]),
    failure: fn (PaymentError $error) => back()->withErrors(['payment' => $error->message()]),
);
```

## Why not exceptions?

Exceptions are great for exceptional situations — the database is down, the disk
is full. But most failures in an application are ordinary: the card is expired,
the item is out of stock, the address is outside the delivery zone. Modeling
those with exceptions has real costs:

- **They're invisible.** Nothing in a method's signature says what it throws, so
  callers find out from the source — or from production.
- **Nothing forces handling.** Forget a try/catch and the failure rockets up the
  stack to a generic 500.
- **try/catch reads badly** for outcomes you *expect* to happen, and catch blocks
  tend to catch more than they meant to.
- **The reason gets locked inside an exception class**, even when an enum or a
  small value object would say it better.

A Result fixes all four: the failure is part of the return type, you have to go
through the result to reach the value, handling reads like normal code, and the
error can be any value that serves the caller — an enum, a value object with
context, a string, or an exception if you already have one.

Everything is fully typed end to end, so your IDE and static analyser always know
both the value type and the error type — through every transformation.

## Installation

```bash
composer require iak/result
```

Requires PHP 8.2+. No dependencies — works in any PHP project, Laravel or not.

## Returning results

```php
Result::success($order);                    // it worked, here's the outcome
Result::failure(OrderError::OutOfStock);    // it didn't, here's why
Result::success();                          // it worked, nothing to return
```

## Handling results

Handle both outcomes in one expression with `match()` — both arms are required,
so the failure path can't be forgotten:

```php
$result->match(
    success: fn (Order $order) => redirect()->route('orders.show', $order),
    failure: fn (OrderError $error) => back()->withErrors(['order' => $error->message()]),
);
```

Or guard first and keep the happy path flat — after the check, `value()` is
guaranteed to succeed:

```php
if ($result->isFailure()) {
    Log::info('Order rejected', ['reason' => $result->error()->name]);

    return back()->withErrors(['order' => $result->error()->message()]);
}

$order = $result->value();
```

When you just need a fallback, skip the ceremony:

```php
$eta = $estimate->handle($address)->valueOr(45);                    // fixed default
$eta = $estimate->handle($address)->valueOrElse(fn ($error) => $error->retryAfter());
```

## Composing

Transform outcomes without unpacking them — a failure passes through untouched,
a success passes through `mapError` untouched:

```php
$result->map(fn (Order $order) => $order->total);
$result->mapError(fn (GatewayError $e) => PaymentError::fromGateway($e));

// side effects (logging, metrics) that leave the result as-is:
$result->tap(fn (Order $order) => Log::info('created', ['id' => $order->id]))
    ->tapError(fn (OrderError $e) => report($e));
```

`chain()` pipes the value into the next fallible step. The first failure
short-circuits everything after it and falls through to the end, so a whole
workflow needs exactly one failure handler:

```php
return CreateOrder::make()->handle($cart)                           // Result<Order, ValidationError>
    ->chain(fn (Order $o) => ChargeCustomer::make()->handle($o))    // Result<Receipt, ValidationError|PaymentError>
    ->orElse(fn ($error) => RetryPayment::make()->handle($cart))    // recover and continue
    ->match(
        success: fn (Receipt $receipt) => response()->json($receipt),
        failure: fn (ValidationError|PaymentError $e) => response()->json([], 422),
    );
```

Notice the error types accumulate: add a step with a new error type and every
`match` downstream has to account for it.

> **Why `chain()` and not `then()`?** Promise libraries (Guzzle — and therefore
> Laravel's `Http::async()`/`Http::pool()` — and ReactPHP) treat any object with a
> public `then()` method as a promise and try to resolve it. A Result named that way
> would hang or crash promise pipelines.

## Combining many results

`Result::all()` turns a collection of results into one: a success holding every
value (keys preserved) when all succeed, or the first failure — returned as-is —
when any fails. It accepts any iterable and stops consuming it at the first failure.

```php
$result = Result::all([
    'order'   => CreateOrder::make()->handle($cart),
    'invoice' => CreateInvoice::make()->handle($cart),
]);
// success: ['order' => Order, 'invoice' => Invoice] — or the first failure
```

## When you're sure

Sometimes you've already ruled failure out and just want the value. `value()` and
`error()` are the escape hatches — on the wrong variant they throw a
`ResultException` carrying the offending value (`->value`), with the original
exception chained as `->getPrevious()` when the error is a `Throwable`:

```php
$order = $result->value();                        // Order, or ResultException
$order = $result->expect('cart was validated');   // same, with your message
```

An unguarded `value()` reads as an assertion: "this can't fail here, blame me if
it does." Use it sparingly.

## Use with [iak/action](https://github.com/iaK/action)

Actions that can fail return a `Result` from `handle()` — the signature documents
every possible outcome:

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
subsequent calls. If you want a failed outcome to be retryable, forget the key on
the failure branch:

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

## Good to know

- **Immutable** — transformations never mutate; they always return a result, never
  modify one in place.
- **Structural equality** — `Result::success(1) == Result::success(1)` is `true`.
- **Sealed** — `Success` and `Failure` are final; `isFailure() === false` always
  means success.
- **Prefer `isSuccess()`/`isFailure()` over `instanceof`** — static analysers can't
  carry the value/error types through a bare `instanceof`, but the methods keep
  them intact.

## Development

```bash
composer test      # Pest
composer analyse   # PHPStan, including the type-inference fixtures in types/
composer format    # Pint
```

## License

MIT. See [LICENSE.md](LICENSE.md).
