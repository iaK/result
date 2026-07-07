# iak/result — Design

**Date:** 2026-07-07
**Status:** Approved

## Purpose

A Rust-inspired `Result<T, E>` type for PHP, published as `iak/result`. Failures become
values instead of exceptions: a function that can fail returns `Result<T, E>` and the
caller is forced — by PHPStan at level 9 — to handle both variants. The flagship use
case is actions built on `iak/action` returning `Result` from `handle()`.

## Decisions (settled during brainstorming)

1. **Error side `E` is unconstrained** — enums, value objects, strings, or Throwables.
   True Rust semantics; domain errors do not have to be exception classes.
2. **Result-native integration only** — no exception-catching bridge (`Result::try()`,
   `Tryable` trait) is shipped. The convention is: fallible actions return `Result`
   from `handle()`. Integration with `iak/action` is documentation, not code.
3. **API surface: essentials + `match`** — 15 methods (listed below). No
   `inspect`/`and`/`or`/`mapOr`/`flatten`; easy to add later if needed.
4. **Pure PHP package** — `require: { "php": "^8.2" }` only. No illuminate
   dependencies, no service provider. Works in any PHP project, including Laravel.
5. **Architecture: sealed hierarchy** — abstract `Result<T, E>` with final `Ok<T>` and
   `Err<E>` subclasses, covariant templates, `never` for the absent side.

## Package identity

- Composer name: `iak/result`
- Namespace: `Iak\Result`
- PHP: `^8.2`
- License/author conventions: same as `iak/action` (MIT, Isak Berglind)

## Architecture

```
src/
  Result.php           abstract base; @template-covariant T, @template-covariant E, @immutable
  Ok.php               final; @extends Result<T, never>; readonly payload
  Err.php              final; @extends Result<never, E>; readonly payload
  UnwrapException.php  final; extends RuntimeException; the only exception the package throws
```

Key type-level mechanics:

- **Covariance + `never`:** `Ok<T>` extends `Result<T, never>` and `Err<E>` extends
  `Result<never, E>`. With covariant templates, `Result::ok($receipt)` satisfies
  `@return Result<Receipt, PaymentError>` for any `E` (and symmetrically for `Err`).
  Userland never needs casts or `@var` annotations.
- **Narrowing:** `isOk()` carries `@phpstan-assert-if-true Ok<T> $this` and
  `@phpstan-assert-if-false Err<E> $this` (mirrored on `isErr()`). After a check,
  `unwrap()` is provably safe. `instanceof Ok` / `instanceof Err` also narrows.
- **`never` returns:** `Err::unwrap()` and `Ok::unwrapErr()` (and the corresponding
  `expect*`) are declared `: never` natively — PHP allows narrowing `mixed` to `never`
  in overrides, and PHPStan then treats code after them as dead.
- Constructors: `Result::ok()` / `Result::err()` static factories; `new Ok(...)` /
  `new Err(...)` remain public and equivalent.

## API surface (complete)

| Method | Type-level signature | Behavior |
|---|---|---|
| `Result::ok($value = null)` | `static<TOk>(TOk = null): Ok<TOk>` | Omitted arg → `Ok<null>` (unit case). |
| `Result::err($error)` | `static<TErr>(TErr): Err<TErr>` | |
| `isOk()` | `(): bool` + assert-if-true `Ok<T>`, assert-if-false `Err<E>` | |
| `isErr()` | `(): bool` + mirrored assertions | |
| `unwrap()` | `(): T`; on `Err`: `(): never` | Throws `UnwrapException` on `Err`. |
| `unwrapErr()` | `(): E`; on `Ok`: `(): never` | Throws `UnwrapException` on `Ok`. |
| `expect(string $message)` | `(): T`; on `Err`: `(): never` | Throws with the caller's message. |
| `expectErr(string $message)` | `(): E`; on `Ok`: `(): never` | Throws with the caller's message. |
| `unwrapOr($default)` | `<TDefault>(TDefault): T\|TDefault` | |
| `unwrapOrElse($fn)` | `<TDefault>(callable(E): TDefault): T\|TDefault` | Lazy; receives the error value. |
| `map($fn)` | `<U>(callable(T): U): Result<U, E>` | No-op on `Err`. |
| `mapErr($fn)` | `<F>(callable(E): F): Result<T, F>` | No-op on `Ok`. |
| `andThen($fn)` | `<U, F>(callable(T): Result<U, F>): Result<U, E\|F>` | Chaining primitive; error types accumulate as a union. |
| `orElse($fn)` | `<U, F>(callable(E): Result<U, F>): Result<T\|U, F>` | Recovery. |
| `match($ok, $err)` | `<A, B>(callable(T): A, callable(E): B): A\|B` | Named args encouraged (`ok:`, `err:`). `match` is a legal method name in PHP 8. |

## Runtime semantics

### UnwrapException

```php
final class UnwrapException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly mixed $value,     // the offending contained value
        ?Throwable $previous = null,
    ) { ... }
}
```

- Messages include a short debug rendering of the contained value: enum case name
  (`PaymentError::CardExpired`), object class name, or scalar value — the Rust
  panic-message equivalent.
- When the contained value is a `Throwable`, it is chained as `$previous`, preserving
  stack traces even though `E` is unconstrained.
- `expect()`/`expectErr()` use the caller's message; the value still rides on `->value`.

### Immutability, equality, serialization

- `Ok`/`Err` are `final` with `readonly` payloads; all classes annotated `@immutable`.
  Combinators always return new instances. No mutation API exists.
- Structural equality via PHP `==` works out of the box
  (`Result::ok(1) == Result::ok(1)`); documented, no code needed.
- Default PHP serialization works whenever `T`/`E` are serializable. Required for
  `iak/action`'s `idempotent()` on persistent stores.

## iak/action interop (documentation only)

README gets a dedicated "Use with iak/action" section covering:

1. **Result-returning actions:**

```php
class ChargeCustomer extends Action
{
    /** @return Result<Receipt, PaymentError> */
    public function handle(Order $order): Result
    {
        if ($order->cardExpired()) {
            return Result::err(PaymentError::CardExpired);
        }

        return Result::ok($this->gateway->charge($order));
    }
}
```

2. **Chaining actions** — `andThen` pipelines with error-union accumulation ending in
   an exhaustive `match`.
3. **Testing** — `iak/action`'s `Testable<TAction>` mirrors `handle()`, so
   `ChargeCustomer::test()->handle($order)` is typed `Result<Receipt, PaymentError>`
   with no extra work.
4. **Idempotency caveat** — for `idempotent()`, an `Err` return is a *successful* run:
   the key is consumed and the `Err` is cached and replayed. Callers wanting
   retry-on-error semantics should call `forgetIdempotency()` on the `Err` branch.

No dependency on `iak/action`, not even in `require-dev`.

## Testing strategy

Two layers, matching the package's two promises:

1. **Behavior (Pest ^3):** every method on both variants; `UnwrapException` message,
   `->value`, and `$previous` chaining; equality; serialization round-trip; an
   architecture test (pest-plugin-arch) pinning `final` classes and strict types.
2. **Type inference (PHPStan `TypeInferenceTestCase`):** fixture files asserting via
   `PHPStan\Testing\assertType()`:
   - `Result::ok(1)` is `Ok<int>`; `Result::ok()` is `Ok<null>`; `Result::err($e)` is `Err<PaymentError>`
   - narrowing after `isOk()`/`isErr()`/`instanceof`
   - covariant assignment: `Ok<Receipt>` assignable to `Result<Receipt, PaymentError>`
   - `andThen` error-union: `Result<U, E|F>`
   - `unwrapOr`/`unwrapOrElse` producing `T|TDefault`
   - `never` returns making post-`unwrap()` code dead on `Err`

## Tooling & CI

- PHPStan **level 9** over `src/`, plain `phpstan/phpstan` (no larastan), **no baseline**.
- Pest ^3, Laravel Pint, `composer analyse` / `test` / `format` scripts (matching
  `iak/action` conventions).
- GitHub Actions: test + analyse matrix on PHP 8.2 / 8.3 / 8.4.

## Out of scope (explicit YAGNI)

- `Option<T>` type
- Exception-catching bridge (`Result::try()`, `Tryable` trait)
- Extended combinators (`inspect`, `and`, `or`, `mapOr`, `mapOrElse`, `flatten`, `isOkAnd`, …)
- Laravel service provider, Collection macros, response helpers
- A custom PHPStan extension (the design deliberately needs none)
