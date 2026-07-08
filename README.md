# Result

A return type for operations that can fail.

Instead of throwing exceptions for outcomes you expect — the card is expired, the
item is out of stock — you return them. The failure becomes part of the method's
signature, the caller has to deal with it, and your IDE and static analyser know
both outcomes at every step.

## Before and after

You're placing an order. It can fail three ways: the kitchen is closed, an item
is out of stock, the address is outside the delivery zone.

### Before — exceptions

```php
class PlaceOrder
{
    // The signature promises an Order. The three ways this fails
    // are invisible unless you read the whole method.
    public function handle(Cart $cart, Address $address): Order
    {
        if (! $this->kitchen->isOpen()) {
            throw new KitchenClosedException();
        }

        if (! $cart->allItemsAvailable()) {
            throw new OutOfStockException($cart->unavailableItems());
        }

        if (! $this->zones->covers($address)) {
            throw new DeliveryUnavailableException($address);
        }

        return Order::create($cart, $address);
    }
}
```

```php
public function store(StoreOrderRequest $request, PlaceOrder $placeOrder)
{
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
}
```

Three exception classes to write and maintain. Nothing checks that the caller
catches them — forget one (or add a fourth failure mode next month) and the
customer gets a 500. And every caller has to *know* what to catch, because the
signature won't tell them.

### After — results

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

```php
public function store(StoreOrderRequest $request, PlaceOrder $placeOrder)
{
    return $placeOrder->handle($request->cart(), $request->address())->match(
        success: fn (Order $order) => redirect()->route('orders.show', $order),
        failure: fn (OrderError $error) => back()->withErrors(['order' => $error->message()]),
    );
}
```

The signature now declares every outcome. One enum replaces three exception
classes, and the error messages live next to the errors. The failure path can't
be forgotten — there is no way to get the `Order` out without going through the
result. And when you add a fourth failure mode, the enum's own `match` tells you
exactly which handlers need updating.

### Before and after — a multi-step flow

Failures compose. Where exceptions force a try/catch per step:

```php
try {
    $order = $placeOrder->handle($cart, $address);
} catch (OrderException $e) {
    return response()->json(['error' => $e->getMessage()], 422);
}

try {
    $receipt = $chargeCustomer->handle($order, $card);
} catch (PaymentException $e) {
    return response()->json(['error' => $e->getMessage()], 422);
}

return response()->json($receipt);
```

…results chain, and the first failure falls through to a single handler:

```php
return $placeOrder->handle($cart, $address)
    ->chain(fn (Order $order) => $chargeCustomer->handle($order, $card))
    ->match(
        success: fn (Receipt $receipt) => response()->json($receipt),
        failure: fn (OrderError|PaymentError $error) => response()->json(['error' => $error->message()], 422),
    );
```

## Installation

```bash
composer require iak/result
```

Requires PHP 8.2+. No dependencies — works in any PHP project, Laravel or not.

## API

### Creating results

#### `Result::success($value = null)`

The operation worked, here's the outcome. Call it without arguments when there's
nothing meaningful to return:

```php
return Result::success($order);   // Result holding an Order
return Result::success();         // "it worked" — holds null
```

#### `Result::failure($error)`

The operation failed, here's why. The error can be **any value** — an enum for a
fixed set of outcomes, a value object when the caller needs context, a string, or
an exception if you already have one:

```php
return Result::failure(OrderError::OutOfStock);
return Result::failure(new ValidationErrors($messages));
return Result::failure($caughtException);
```

#### `Result::all($results)`

Combine a collection of results into one. All successes → one success holding
every value, keys preserved. Any failure → the first failure, returned as-is.
Accepts any iterable and stops consuming it at the first failure.

```php
$result = Result::all([
    'order'   => $placeOrder->handle($cart, $address),
    'invoice' => $createInvoice->handle($cart),
]);
// success: ['order' => Order, 'invoice' => Invoice] — or the first failure
```

### Checking the outcome

#### `isSuccess()` / `isFailure()`

Guard-style handling, when `match()` would be too heavy or you want an early
return. After the check, extracting is safe — and static analysers know it:

```php
if ($result->isFailure()) {
    return back()->withErrors(['order' => $result->error()->message()]);
}

$order = $result->value(); // guaranteed — no exception possible here
```

### Getting the value out

#### `value()`

The success value. On a failure it throws `ResultException`, so use it after a
guard (see above) — or unguarded as a deliberate assertion: "this can't fail
here, blame me if it does."

```php
$order = $result->value();
```

#### `error()`

The mirror image: the error value, throwing `ResultException` on a success.
Mostly used after an `isFailure()` guard, and in tests:

```php
expect($result->error())->toBe(OrderError::OutOfStock);
```

#### `valueOr($default)`

The value, or a fixed fallback when it failed. For when you don't care *why* it
failed:

```php
$eta = $estimateDelivery->handle($address)->valueOr(45); // minutes
```

#### `valueOrElse($fallback)`

Like `valueOr()`, but the fallback is computed from the error — and only when
actually needed:

```php
$eta = $estimateDelivery->handle($address)
    ->valueOrElse(fn (EstimateError $error) => $error->conservativeGuess());
```

#### `expect($message)` / `expectError($message)`

Like `value()`/`error()`, but the exception carries *your* message — useful when
the assertion deserves an explanation for whoever hits it later:

```php
$order = $result->expect('cart was validated in the previous step');
```

### Transforming

#### `map($fn)`

Transform the success value without unpacking the result. A failure passes
through untouched:

```php
$result->map(fn (Order $order) => $order->total);
// Result<Order, OrderError> → Result<Money, OrderError>
```

#### `mapError($fn)`

Transform the error — typically to translate a low-level error into your
domain's language at a boundary:

```php
$gateway->charge($card)
    ->mapError(fn (GatewayError $error) => PaymentError::fromGateway($error));
```

#### `tap($fn)` / `tapError($fn)`

Run a side effect — logging, metrics, notifications — without touching the
result. The result flows through unchanged, and the callback only runs for its
variant:

```php
return $placeOrder->handle($cart, $address)
    ->tap(fn (Order $order) => Log::info('order placed', ['id' => $order->id]))
    ->tapError(fn (OrderError $error) => Metrics::increment('orders.rejected'));
```

### Chaining fallible steps

#### `chain($fn)`

Pipe the success value into the next operation that can itself fail. The first
failure short-circuits everything after it. Error types accumulate, so the final
handler is forced to know about every step's failure modes:

```php
return $placeOrder->handle($cart, $address)                          // Result<Order, OrderError>
    ->chain(fn (Order $order) => $charge->handle($order, $card))     // Result<Receipt, OrderError|PaymentError>
    ->chain(fn (Receipt $receipt) => $notify->handle($receipt));     // Result<null, OrderError|PaymentError|NotifyError>
```

> Why `chain()` and not `then()`? Promise libraries (Guzzle — and therefore
> Laravel's `Http::async()` — and ReactPHP) treat any object with a public
> `then()` method as a promise and try to resolve it. A Result named that way
> would break inside promise pipelines.

#### `orElse($fn)`

The recovery counterpart: on failure, try something else that can itself succeed
or fail. A success passes through untouched:

```php
$receipt = $chargeCard->handle($order, $card)
    ->orElse(fn (PaymentError $error) => $chargeWallet->handle($order));
```

### Handling both outcomes

#### `match($success, $failure)`

Handle both variants in one expression. Both arms are required, so the failure
path can't be forgotten:

```php
return $result->match(
    success: fn (Order $order) => redirect()->route('orders.show', $order),
    failure: fn (OrderError $error) => back()->withErrors(['order' => $error->message()]),
);
```

## Use with [iak/action](https://github.com/iaK/action)

Actions that can fail return a `Result` from `handle()` — like `PlaceOrder`
above. Testing needs no extra wiring: `PlaceOrder::test()->handle($cart, $address)`
mirrors `handle()` and is typed `Result<Order, OrderError>` automatically.

**Idempotency caveat:** for `->idempotent($key)`, a returned failure is a
*successful* run — the key is consumed and the failure is cached and replayed on
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

- **`ResultException`** — the only exception the package throws (from `value()`,
  `error()`, `expect()`, `expectError()` on the wrong variant). It carries the
  offending value on `->value`, and when the error is itself a `Throwable`, it's
  chained as `->getPrevious()` so stack traces survive.
- **Immutable** — transformations never mutate; they always return a result,
  never modify one in place.
- **Structural equality** — `Result::success(1) == Result::success(1)` is `true`.
- **Sealed** — `Success` and `Failure` are final; `isFailure() === false` always
  means success.
- **Prefer `isSuccess()`/`isFailure()` over `instanceof`** — static analysers
  can't carry the value/error types through a bare `instanceof`, but the methods
  keep them intact.

## Development

```bash
composer test      # Pest
composer analyse   # PHPStan, including the type-inference fixtures in types/
composer format    # Pint
```

## License

MIT. See [LICENSE.md](LICENSE.md).
