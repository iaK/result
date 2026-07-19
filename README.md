# Result

[![Latest Version on Packagist](https://img.shields.io/packagist/v/iak/result.svg?style=flat-square)](https://packagist.org/packages/iak/result)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/iaK/result/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/iaK/result/actions?query=workflow%3ATests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/iaK/result/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/iaK/result/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/iak/result.svg?style=flat-square)](https://packagist.org/packages/iak/result)

A `Result` is a return type for operations that can fail. Instead of throwing an
exception for an outcome you fully expect — an expired card, an empty stock, a
closed kitchen — you return it. For example, check out the following code:

```php
return $placeOrder->handle($cart, $address)
    ->chain(fn (Order $order) => $charge->handle($order, $card))
    ->match(
        success: fn (Receipt $receipt) => response()->json($receipt),
        failure: fn (OrderError|PaymentError $error) => response()->json(['error' => $error->message()], 422),
    );
```

Two operations that can each fail, one place where failure is handled, and not a
try/catch in sight. Every possible outcome is right there in the types — your IDE
sees them, your static analyser sees them, and the next developer sees them.

## Installation

```bash
composer require iak/result
```

That's it. Results require PHP 8.2+ and nothing else — no framework, no
configuration, no service provider.

## Your First Result

Let's build something real: placing an order. You've probably written this
controller a hundred times:

```php
try {
    $order = $placeOrder->handle($request->cart(), $request->address());
} catch (KitchenClosedException) {
    return back()->withErrors(['order' => 'The kitchen is closed right now.']);
} catch (OutOfStockException) {
    return back()->withErrors(['order' => 'Some items are out of stock.']);
} catch (DeliveryUnavailableException) {
    return back()->withErrors(['order' => "We don't deliver to this address yet."]);
}

return redirect()->route('orders.show', $order);
```

It works — until someone adds a fourth failure mode to `PlaceOrder` and this
controller quietly starts responding with 500s. Nothing in `handle()`'s signature
says what it throws, and nothing checks that you caught it all.

Let's rebuild it with a Result. First, give every failure a name. An enum is
perfect for this, and it gives the error messages a home too:

```php
enum OrderError
{
    case KitchenClosed;
    case OutOfStock;
    case DeliveryUnavailable;

    public function message(): string
    {
        return match ($this) {
            self::KitchenClosed       => 'The kitchen is closed right now.',
            self::OutOfStock          => 'Some items are out of stock.',
            self::DeliveryUnavailable => "We don't deliver to this address yet.",
        };
    }
}
```

Next, instead of throwing, return the outcome — either way:

```php
use Iak\Result\Result;

class PlaceOrder
{
    /** @return Result<Order, OrderError> */
    public function handle(Cart $cart, Address $address): Result
    {
        if (! $this->kitchen->isOpen()) {
            return Result::failure(OrderError::KitchenClosed);
        }

        if (! $cart->allItemsAvailable()) {
            return Result::failure(OrderError::OutOfStock);
        }

        if (! $this->zones->covers($address)) {
            return Result::failure(OrderError::DeliveryUnavailable);
        }

        return Result::success(Order::create($cart, $address));
    }
}
```

Notice the docblock: `Result<Order, OrderError>`. That one line now documents
every way this operation can end. No source-diving, no tribal knowledge.

Finally, the controller shrinks to a single expression:

```php
public function store(StoreOrderRequest $request, PlaceOrder $placeOrder)
{
    return $placeOrder->handle($request->cart(), $request->address())->match(
        success: fn (Order $order) => redirect()->route('orders.show', $order),
        failure: fn (OrderError $error) => back()->withErrors(['order' => $error->message()]),
    );
}
```

> [!NOTE]
> **Where did the try/catch go?** There's nothing to catch — failure is just a
> return value now. And unlike a catch block, `match()` can't be forgotten: it's
> the only way to get at the order, and it requires both arms.

And when someone adds that fourth failure mode? They add an enum case, the
`match` inside `message()` immediately demands a message for it, and every
consumer of the error gets flagged by static analysis. The failure mode is born
handled.

That's the whole pattern. Everything else in this package is convenience on top
of it.

## Available Methods

[all](#all) · [chain](#chain) · [error](#error) · [expect](#expect) ·
[expectError](#experror) · [failure](#failure) · [isFailure](#isfailure) ·
[isSuccess](#issuccess) · [map](#map) · [mapError](#maperror) · [match](#match) ·
[orElse](#orelse) · [success](#success) · [tap](#tap) · [tapError](#taperror) ·
[value](#value) · [valueOr](#valueor) · [valueOrElse](#valueorelse)

## Method Listing

<a name="success"></a>
### `success()`

The static `success` method wraps a value in a successful result:

```php
return Result::success($order);
```

You may call it without arguments when the operation has nothing meaningful to
return — "it worked" is the whole message:

```php
return Result::success();
```

<a name="failure"></a>
### `failure()`

The static `failure` method wraps an error in a failed result. The error may be
anything: an enum, a value object carrying context, a string, or an exception if
you already have one:

```php
return Result::failure(OrderError::OutOfStock);
return Result::failure(new AddressOutsideZone($address, $nearestZone));
return Result::failure($caughtException);
```

<a name="all"></a>
### `all()`

The static `all` method combines a collection of results into a single result.
If every result succeeded, you get one success holding all the values with their
keys preserved. If any failed, you get the first failure back, untouched:

```php
$result = Result::all([
    'order'   => $placeOrder->handle($cart, $address),
    'invoice' => $createInvoice->handle($cart),
]);

// success: ['order' => Order, 'invoice' => Invoice]
// failure: whichever failed first
```

You may pass any iterable. Iteration stops at the first failure, so a lazy
generator won't do more work than necessary.

<a name="issuccess"></a>
### `isSuccess()`

The `isSuccess` method determines whether the operation succeeded:

```php
if ($result->isSuccess()) {
    $order = $result->value(); // safe — and your static analyser agrees
}
```

<a name="isfailure"></a>
### `isFailure()`

The `isFailure` method is the mirror of [`isSuccess`](#issuccess). It shines in
guard clauses, keeping the happy path flat:

```php
if ($result->isFailure()) {
    return back()->withErrors(['order' => $result->error()->message()]);
}

$order = $result->value();
```

> [!NOTE]
> Prefer these methods over `instanceof` checks. Static analysers can't carry
> the value and error types through a bare `instanceof`, but `isSuccess()` and
> `isFailure()` keep them intact.

<a name="value"></a>
### `value()`

The `value` method returns the success value:

```php
$order = $result->value();
```

If the result is a failure, `value` throws a `ResultException`. An unguarded
call is therefore an assertion — "this can't fail here, and if I'm wrong I want
to hear about it." When you're not asserting, guard with
[`isFailure`](#isfailure) first or reach for [`valueOr`](#valueor).

<a name="error"></a>
### `error()`

The `error` method returns the error value, throwing a `ResultException` if the
result is actually a success. You'll use it after a guard, and all over your
tests:

```php
expect($result->error())->toBe(OrderError::OutOfStock);
```

<a name="expect"></a>
### `expect()`

The `expect` method works like [`value`](#value), but the exception carries your
message — so when the impossible happens, whoever's on call knows what you were
assuming:

```php
$order = $result->expect('cart was validated in the previous step');
```

<a name="experror"></a>
### `expectError()`

The `expectError` method is [`expect`](#expect) for the error side:

```php
$error = $result->expectError('the gateway was stubbed to fail in this test');
```

<a name="valueor"></a>
### `valueOr()`

The `valueOr` method returns the success value, or your fallback if the
operation failed — for when you don't care why:

```php
$eta = $estimateDelivery->handle($address)->valueOr(45);
```

<a name="valueorelse"></a>
### `valueOrElse()`

The `valueOrElse` method computes the fallback from the error, and only when
it's actually needed:

```php
$eta = $estimateDelivery->handle($address)
    ->valueOrElse(fn (EstimateError $error) => $error->conservativeGuess());
```

<a name="map"></a>
### `map()`

The `map` method transforms the success value without unpacking the result. A
failure passes through untouched:

```php
$result->map(fn (Order $order) => $order->total);
// Result<Order, OrderError> becomes Result<Money, OrderError>
```

<a name="maperror"></a>
### `mapError()`

The `mapError` method transforms the error instead. It's the tool for
translating low-level errors into your domain's language at a boundary:

```php
$gateway->charge($card)
    ->mapError(fn (GatewayError $error) => PaymentError::fromGateway($error));
```

<a name="tap"></a>
### `tap()`

The `tap` method runs a side effect on the success value — logging, metrics,
notifications — and hands the result back unchanged:

```php
return $placeOrder->handle($cart, $address)
    ->tap(fn (Order $order) => Log::info('order placed', ['id' => $order->id]));
```

<a name="taperror"></a>
### `tapError()`

The `tapError` method does the same for the failure side. Together they let you
observe a pipeline without interrupting it:

```php
return $placeOrder->handle($cart, $address)
    ->tap(fn (Order $order) => Log::info('order placed', ['id' => $order->id]))
    ->tapError(fn (OrderError $error) => Metrics::increment('orders.rejected'));
```

<a name="chain"></a>
### `chain()`

The `chain` method pipes the success value into the next operation that can
itself fail. The first failure short-circuits everything after it, so a whole
workflow needs exactly one failure handler:

```php
return $placeOrder->handle($cart, $address)                       // Result<Order, OrderError>
    ->chain(fn (Order $order) => $charge->handle($order, $card))  // Result<Receipt, OrderError|PaymentError>
    ->match(
        success: fn (Receipt $receipt) => response()->json($receipt),
        failure: fn (OrderError|PaymentError $error) => response()->json(['error' => $error->message()], 422),
    );
```

Notice how the error types stack up: add a step, and every handler downstream is
made aware of what it might have to deal with.

> [!NOTE]
> **Why isn't this called `then()`?** Promise libraries — Guzzle, and therefore
> Laravel's `Http::async()`, and ReactPHP — treat any object with a public
> `then()` method as a promise and try to resolve it. A Result named that way
> would break inside promise pipelines, so it's `chain()`.

<a name="orelse"></a>
### `orElse()`

The `orElse` method is recovery: on failure, try something else that can itself
succeed or fail. A success passes straight through:

```php
$receipt = $chargeCard->handle($order, $card)
    ->orElse(fn (PaymentError $error) => $chargeWallet->handle($order));
```

<a name="match"></a>
### `match()`

The `match` method handles both outcomes in one expression. Both arms are
required — the failure path can't be forgotten:

```php
return $result->match(
    success: fn (Order $order) => redirect()->route('orders.show', $order),
    failure: fn (OrderError $error) => back()->withErrors(['order' => $error->message()]),
);
```

## Handling Different Error Types

Once you start chaining, the error side becomes a union — `OrderError|PaymentError`
— and you may want to treat them differently. PHP's own `match` has you covered:

```php
$result->match(
    success: fn (Receipt $receipt) => response()->json($receipt),
    failure: fn (OrderError|PaymentError $error) => match (true) {
        $error instanceof OrderError   => back()->withErrors(['order' => $error->message()]),
        $error instanceof PaymentError => $this->redirectToPaymentRetry($error),
    },
);
```

Static analysers check that inner `match` against the union — add a third error
type to the chain and this exact spot gets flagged until you handle it.

If every error is handled the same way, skip the dispatch entirely and give your
errors a shared interface:

```php
interface DomainError
{
    public function message(): string;
}

// enum OrderError implements DomainError { ... }
// enum PaymentError implements DomainError { ... }

failure: fn (DomainError $error) => back()->withErrors(['order' => $error->message()]),
```

And when a union grows past comfort, normalize early: use
[`mapError`](#maperror) at each boundary so downstream code only ever sees one
error type.

## Good to Know

- **Results are immutable.** Transformations never modify a result in place —
  they hand you a new one (or the same one, untouched).
- **Results compare structurally.** `Result::success(1) == Result::success(1)`
  is `true`.
- **Results serialize.** As long as the contained value serializes, a result
  survives caches and queues.
- **Results are sealed.** `Success` and `Failure` are final, so
  `isFailure() === false` always means success.
- **`ResultException` is the only exception this package throws** — from
  [`value`](#value), [`error`](#error), [`expect`](#expect) and
  [`expectError`](#experror) on the wrong variant. It carries the offending
  value on `->value`, and when the error is itself a `Throwable`, you'll find it
  chained on `->getPrevious()` with its stack trace intact.

## Development

```bash
composer test      # Pest
composer analyse   # PHPStan, including the type-inference fixtures in types/
composer format    # Pint
```

## License

MIT. See [LICENSE.md](LICENSE.md).
