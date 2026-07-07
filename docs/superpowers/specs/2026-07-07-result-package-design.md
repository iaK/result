# iak/result — Design

**Date:** 2026-07-07
**Status:** Approved (naming revised after review — see Decision 6)

## Purpose

A Result type for PHP, published as `iak/result`, with Rust-inspired *semantics* and
PHP-natural *naming*. Failures become values instead of exceptions: a function that can
fail returns `Result<T, E>` and the caller is forced — by PHPStan at level 9 — to
handle both variants. The flagship use case is actions built on `iak/action` returning
`Result` from `handle()`.

## Decisions (settled during brainstorming)

1. **Error side `E` is unconstrained** — enums, value objects, strings, or Throwables.
   Domain errors do not have to be exception classes.
2. **Result-native integration only** — no exception-catching bridge (`Result::try()`,
   `Tryable` trait) is shipped. The convention is: fallible actions return `Result`
   from `handle()`. Integration with `iak/action` is documentation, not code.
3. **API surface: essentials + `match`** — 15 methods (listed below). No
   `inspect`/`and`/`or`/`mapOr`/`flatten`; easy to add later if needed.
4. **Pure PHP package** — `require: { "php": "^8.2" }` only. No illuminate
   dependencies, no service provider. Works in any PHP project, including Laravel.
5. **Architecture: sealed hierarchy** — abstract `Result<T, E>` with final variant
   subclasses, covariant templates, `never` for the absent side.
6. **Naming: PHP-natural vocabulary, not Rust's** (revised from the original Rust
   names after review):
   - Variants are `Success`/`Failure`; payloads are `value`/`error`. Rule: methods
     about the *variant* use Success/Failure words (`isSuccess()`, `isFailure()`),
     methods about the *payload* use value/error words (`value()`, `error()`,
     `valueOr()`, `mapError()`).
   - The chaining method is **`chain()`**, not `then()`: promise libraries
     (Guzzle — and therefore Laravel `Http::async()`/`Http::pool()` — and ReactPHP)
     duck-type any object with a public `then()` method as a thenable and try to
     assimilate it, which would hang or TypeError on a Result. Not `andThen()` since
     the Rust vocabulary was dropped.
   - The variant class is `Failure`, not `Error`: `use Iak\Result\Error` would
     silently shadow PHP's built-in `\Error` in `catch`/`instanceof` within the
     importing file.
   - The instance extractor cannot be named `failure()` because the static
     constructor `Result::failure()` occupies that name (PHP cannot declare both);
     hence the payload extractor is `error()`.
   - The exception is `ResultException` (no method named "unwrap" exists anymore).

## Package identity

- Composer name: `iak/result`
- Namespace: `Iak\Result`
- PHP: `^8.2`
- License/author conventions: same as `iak/action` (MIT, Isak Berglind)

## Architecture

```
src/
  Result.php           abstract base; @template-covariant T, @template-covariant E, @immutable
  Success.php          final; @extends Result<T, never>; readonly payload
  Failure.php          final; @extends Result<never, E>; readonly payload
  ResultException.php  final; extends RuntimeException; the only exception the package throws
```

Key type-level mechanics:

- **Covariance + `never`:** `Success<T>` extends `Result<T, never>` and `Failure<E>`
  extends `Result<never, E>`. With covariant templates, `Result::success($receipt)`
  satisfies `@return Result<Receipt, PaymentError>` for any `E` (and symmetrically for
  `Failure`). Userland never needs casts or `@var` annotations.
- **Narrowing:** `isSuccess()` carries `@phpstan-assert-if-true Success<T> $this` and
  `@phpstan-assert-if-false Failure<E> $this` (mirrored on `isFailure()`). After a
  check, `value()` is provably safe. Note: bare `instanceof Success`/`instanceof
  Failure` identifies the variant but PHPStan does not carry the payload generics
  through it (engine limitation, verified against PHPStan 2.2.5) — the methods are
  the supported narrowing mechanism, and the docs steer users to them.
- **`never` returns:** `Failure::value()` and `Success::error()` (and the
  corresponding `expect*`) are declared `: never` natively — PHP allows narrowing
  `mixed` to `never` in overrides, and PHPStan then treats code after them as dead.
- Constructors: `Result::success()` / `Result::failure()` static factories;
  `new Success(...)` / `new Failure(...)` remain public and equivalent.

## API surface (complete)

| Method | Type-level signature | Behavior |
|---|---|---|
| `Result::success($value = null)` | `static<TVal>(TVal = null): Success<TVal>` | Omitted arg → `Success<null>` (unit case). |
| `Result::failure($error)` | `static<TErr>(TErr): Failure<TErr>` | |
| `isSuccess()` | `(): bool` + assert-if-true `Success<T>`, assert-if-false `Failure<E>` | |
| `isFailure()` | `(): bool` + mirrored assertions | |
| `value()` | `(): T`; on `Failure`: `(): never` | Throws `ResultException` on `Failure`. |
| `error()` | `(): E`; on `Success`: `(): never` | Throws `ResultException` on `Success`. |
| `expect(string $message)` | `(): T`; on `Failure`: `(): never` | Throws with the caller's message. |
| `expectError(string $message)` | `(): E`; on `Success`: `(): never` | Throws with the caller's message. |
| `valueOr($default)` | `<TDefault>(TDefault): T\|TDefault` | |
| `valueOrElse($fn)` | `<TDefault>(callable(E): TDefault): T\|TDefault` | Lazy; receives the error value. |
| `map($fn)` | `<U>(callable(T): U): Result<U, E>` | No-op on `Failure`. |
| `mapError($fn)` | `<F>(callable(E): F): Result<T, F>` | No-op on `Success`. |
| `chain($fn)` | `<U, F>(callable(T): Result<U, F>): Result<U, E\|F>` | Chaining primitive; error types accumulate as a union. Named `chain` to avoid promise thenable duck-typing (see Decision 6). |
| `orElse($fn)` | `<U, F>(callable(E): Result<U, F>): Result<T\|U, F>` | Recovery. |
| `match($success, $failure)` | `<A, B>(callable(T): A, callable(E): B): A\|B` | Named args encouraged (`success:`, `failure:`). `match` is a legal method name in PHP 8. |

## Runtime semantics

### ResultException

```php
final class ResultException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly mixed $value,     // the offending contained value
        ?Throwable $previous = null,
    ) { ... }
}
```

- Messages include a short debug rendering of the contained value: enum case name
  (`PaymentError::CardExpired`), object class name, or scalar value.
- When the contained value is a `Throwable`, it is chained as `$previous`, preserving
  stack traces even though `E` is unconstrained.
- `expect()`/`expectError()` use the caller's message; the value still rides on
  `->value`.

### Immutability, equality, serialization

- `Success`/`Failure` are `final` with `readonly` payloads; all classes annotated
  `@immutable`. Combinators always return new instances. No mutation API exists.
- Structural equality via PHP `==` works out of the box
  (`Result::success(1) == Result::success(1)`); documented, no code needed.
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
            return Result::failure(PaymentError::CardExpired);
        }

        return Result::success($this->gateway->charge($order));
    }
}
```

2. **Chaining actions** — `chain()` pipelines with error-union accumulation ending in
   an exhaustive `match(success:, failure:)`.
3. **Testing** — `iak/action`'s `Testable<TAction>` mirrors `handle()`, so
   `ChargeCustomer::test()->handle($order)` is typed `Result<Receipt, PaymentError>`
   with no extra work.
4. **Idempotency caveat** — for `idempotent()`, a `Failure` return is a *successful*
   run: the key is consumed and the `Failure` is cached and replayed. Callers wanting
   retry-on-error semantics should call `forgetIdempotency()` on the `Failure` branch.

No dependency on `iak/action`, not even in `require-dev`.

## Testing strategy

Two layers, matching the package's two promises:

1. **Behavior (Pest ^3):** every method on both variants; `ResultException` message,
   `->value`, and `$previous` chaining; equality; serialization round-trip; an
   architecture test (pest-plugin-arch) pinning `final` classes and strict types.
2. **Type inference:** fixture files under `types/` asserting via
   `PHPStan\Testing\assertType()`, verified by PHPStan during **regular analysis**
   (`types/` is included in the phpstan.neon paths, so `composer analyse` checks the
   assertions — no `TypeInferenceTestCase` harness needed):
   - `Result::success(1)` is `Success<int>`; `Result::success()` is `Success<null>`;
     `Result::failure($e)` is `Failure<PaymentError>`
   - narrowing after `isSuccess()`/`isFailure()`
   - covariant assignment: `Success<Receipt>` assignable to `Result<Receipt, PaymentError>`
   - `chain()` error-union: `Result<U, E|F>`
   - `valueOr`/`valueOrElse` producing `T|TDefault`
   - `never` returns making post-`value()` code dead on `Failure`

## Tooling & CI

- PHPStan **level 9** over `src/` and `types/`, plain `phpstan/phpstan` `^2.1`
  (2.1+ fixes `@phpstan-assert-if-true` on `$this` in abstract classes), **no baseline**.
- Pest ^3, Laravel Pint, `composer analyse` / `test` / `format` scripts (matching
  `iak/action` conventions).
- GitHub Actions: test + analyse matrix on PHP 8.2 / 8.3 / 8.4.

## Out of scope (explicit YAGNI)

- `Option<T>` type
- Exception-catching bridge (`Result::try()`, `Tryable` trait)
- Extended combinators (`inspect`, `and`, `or`, `mapOr`, `mapOrElse`, `flatten`, …)
- Laravel service provider, Collection macros, response helpers
- A custom PHPStan extension (the design deliberately needs none)
