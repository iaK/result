# iak/result Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `iak/result` — a zero-dependency PHP Result type (`Result<T, E>` with sealed `Success`/`Failure` variants) that is fully inferable under PHPStan level 9.

**Architecture:** Abstract `Result<T, E>` with covariant templates; `Success<T> extends Result<T, never>` and `Failure<E> extends Result<never, E>` so either variant is assignable to any compatible `Result`. All generic PHPDoc lives on the abstract class; the final variants carry **no method PHPDoc** (they inherit it with templates bound) except constructor `@param`. Type inference is guarded by `assertType()` fixture files in `types/`, checked by PHPStan during regular analysis.

**Tech Stack:** PHP ^8.2, phpstan/phpstan ^2.1 (plain, no larastan, no baseline), pestphp/pest ^3.0 + pest-plugin-arch, laravel/pint, GitHub Actions.

## Global Constraints

- Composer name `iak/result`, namespace `Iak\Result\` → `src/`, MIT, author Isak Berglind <isak@berglind.dev>.
- Runtime requirements: `"php": "^8.2"` and NOTHING else. No illuminate packages, no service provider.
- PHPStan level 9 over `src/` and `types/`; `composer analyse` must be clean with **no baseline** at the end of every task.
- Every PHP file starts with `<?php` + `declare(strict_types=1);`.
- `Success`, `Failure`, `ResultException` are `final`. `Result` is `abstract`. All are `@immutable` with `readonly` payloads.
- Naming rule: variant-level words are Success/Failure (`isSuccess()`), payload-level words are value/error (`value()`, `mapError()`). No Rust vocabulary (`unwrap`, `andThen`, `Ok`, `Err`) anywhere, including docs.
- The chaining method is `chain()` — never `then()` (promise libraries duck-type `then()` and would assimilate Results).
- The ONLY exception this package throws is `Iak\Result\ResultException`.
- Prerequisites: local PHP ≥ 8.2 and Composer 2 (`php -v`, `composer -V` to confirm).
- Commit after every task with the message given in its final step; end each commit message with the `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>` trailer.

---

### Task 1: Package scaffolding

**Files:**
- Create: `composer.json`
- Create: `phpstan.neon`
- Create: `phpunit.xml`
- Create: `tests/Pest.php`
- Create: `pint.json`
- Create: `.gitignore`
- Create: `LICENSE.md`

**Interfaces:**
- Consumes: nothing (repo contains only `docs/`).
- Produces: a bootable package skeleton. Later tasks rely on: autoload `Iak\Result\` → `src/`, dev autoload `Iak\Result\Tests\` → `tests/`, and composer scripts `analyse`, `test`, `format`.

- [ ] **Step 1: Write composer.json**

```json
{
    "name": "iak/result",
    "description": "A fully typed Result object for PHP — Rust-inspired semantics with PHP-natural naming",
    "keywords": ["php", "result", "result-type", "error-handling", "phpstan"],
    "homepage": "https://github.com/iaK/result",
    "license": "MIT",
    "authors": [
        {
            "name": "Isak Berglind",
            "email": "isak@berglind.dev",
            "role": "Developer"
        }
    ],
    "require": {
        "php": "^8.2"
    },
    "require-dev": {
        "laravel/pint": "^1.14",
        "pestphp/pest": "^3.0",
        "pestphp/pest-plugin-arch": "^3.0",
        "phpstan/phpstan": "^2.1"
    },
    "autoload": {
        "psr-4": {
            "Iak\\Result\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Iak\\Result\\Tests\\": "tests/"
        }
    },
    "scripts": {
        "analyse": "vendor/bin/phpstan analyse",
        "test": "vendor/bin/pest",
        "format": "vendor/bin/pint"
    },
    "config": {
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true
        }
    },
    "minimum-stability": "stable"
}
```

- [ ] **Step 2: Write phpstan.neon**

Note: paths list only `src` for now; Task 9 adds `types`.

```neon
parameters:
    level: 9
    paths:
        - src
```

- [ ] **Step 3: Write phpunit.xml**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="Package">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

- [ ] **Step 4: Write tests/Pest.php**

```php
<?php

declare(strict_types=1);
```

- [ ] **Step 5: Write pint.json**

```json
{
    "preset": "laravel",
    "rules": {
        "declare_strict_types": true
    }
}
```

- [ ] **Step 6: Write .gitignore**

```
/vendor
/build
composer.lock
.phpunit.cache
.phpunit.result.cache
```

- [ ] **Step 7: Write LICENSE.md**

```markdown
# The MIT License (MIT)

Copyright (c) Isak Berglind <isak@berglind.dev>

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
```

- [ ] **Step 8: Install dependencies**

Run: `composer install --no-interaction`
Expected: ends with `Generating autoload files` (and Pest's plugin activation), exit code 0.

- [ ] **Step 9: Verify the tools boot**

Run: `vendor/bin/pest --version && vendor/bin/phpstan --version && vendor/bin/pint --test`
Expected: Pest 3.x, PHPStan 2.1.x version strings; pint exits 0 (no files to inspect yet). Do NOT run `composer analyse` yet — `src/` doesn't exist until Task 2.

- [ ] **Step 10: Commit**

```bash
git add composer.json phpstan.neon phpunit.xml tests/Pest.php pint.json .gitignore LICENSE.md
git commit -m "chore: scaffold iak/result package"
```

---

### Task 2: ResultException

**Files:**
- Create: `src/ResultException.php`
- Create: `tests/Fixtures/TestError.php`
- Test: `tests/ResultExceptionTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces (used by Tasks 3–4):
  - `new ResultException(string $message, mixed $value = null, ?Throwable $previous = null)` with public readonly `$value`
  - `ResultException::withMessage(string $message, mixed $value): self` — message verbatim, chains `$previous` when `$value instanceof Throwable`
  - `ResultException::describing(string $problem, mixed $value): self` — appends ` (contained: <rendering>).` to `$problem`
  - `Iak\Result\Tests\Fixtures\TestError` — pure enum with cases `CardExpired`, `InsufficientFunds`

- [ ] **Step 1: Write the test fixture enum**

`tests/Fixtures/TestError.php`:

```php
<?php

declare(strict_types=1);

namespace Iak\Result\Tests\Fixtures;

enum TestError
{
    case CardExpired;
    case InsufficientFunds;
}
```

- [ ] **Step 2: Write the failing tests**

`tests/ResultExceptionTest.php`:

```php
<?php

declare(strict_types=1);

use Iak\Result\ResultException;
use Iak\Result\Tests\Fixtures\TestError;

it('carries the message and the contained value', function () {
    $exception = new ResultException('Something went wrong', 42);

    expect($exception)->toBeInstanceOf(RuntimeException::class)
        ->and($exception->getMessage())->toBe('Something went wrong')
        ->and($exception->value)->toBe(42);
});

it('uses the caller message verbatim in withMessage', function () {
    $exception = ResultException::withMessage('Order must be placeable', 'nope');

    expect($exception->getMessage())->toBe('Order must be placeable')
        ->and($exception->value)->toBe('nope');
});

it('chains a throwable value as the previous exception', function () {
    $error = new LogicException('boom');

    $exception = ResultException::withMessage('Charge failed', $error);

    expect($exception->getPrevious())->toBe($error)
        ->and($exception->value)->toBe($error);
});

it('does not chain non-throwable values', function () {
    $exception = ResultException::withMessage('Charge failed', 'card_expired');

    expect($exception->getPrevious())->toBeNull();
});

it('describes enum values with class and case name', function () {
    $exception = ResultException::describing('Cannot get the value of a failure result', TestError::CardExpired);

    expect($exception->getMessage())->toBe(
        'Cannot get the value of a failure result (contained: Iak\Result\Tests\Fixtures\TestError::CardExpired).'
    );
});

it('describes scalar, object, array and null values', function (mixed $value, string $rendered) {
    $exception = ResultException::describing('Problem', $value);

    expect($exception->getMessage())->toBe("Problem (contained: {$rendered}).");
})->with([
    'string' => ['card_expired', '"card_expired"'],
    'int' => [42, '42'],
    'float' => [1.5, '1.5'],
    'bool' => [true, 'true'],
    'null' => [null, 'null'],
    'object' => [new stdClass, 'stdClass'],
    'array' => [[1, 2], 'array'],
]);
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/pest tests/ResultExceptionTest.php`
Expected: FAIL — `Error: Class "Iak\Result\ResultException" not found`.

- [ ] **Step 4: Implement ResultException**

`src/ResultException.php`:

```php
<?php

declare(strict_types=1);

namespace Iak\Result;

use RuntimeException;
use Throwable;
use UnitEnum;

/**
 * Thrown when a result's payload is read from the wrong variant, e.g.
 * value() on a failure. Carries the offending contained value, and chains
 * it as the previous exception when it happens to be a Throwable.
 */
final class ResultException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly mixed $value = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Build an exception whose message is authored by the caller,
     * as in expect() and expectError().
     */
    public static function withMessage(string $message, mixed $value): self
    {
        return new self($message, $value, $value instanceof Throwable ? $value : null);
    }

    /**
     * Build an exception for a wrong-variant access, appending a short
     * rendering of the contained value to the problem description.
     */
    public static function describing(string $problem, mixed $value): self
    {
        return self::withMessage(sprintf('%s (contained: %s).', $problem, self::render($value)), $value);
    }

    private static function render(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            $value instanceof UnitEnum => $value::class.'::'.$value->name,
            is_object($value) => $value::class,
            is_string($value) => '"'.$value.'"',
            is_scalar($value) => var_export($value, true),
            default => get_debug_type($value),
        };
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/pest tests/ResultExceptionTest.php`
Expected: PASS (12 assertions across 6 tests + dataset).

- [ ] **Step 6: Analyse and format**

Run: `composer analyse && vendor/bin/pint --test`
Expected: `[OK] No errors` from PHPStan; pint reports no style issues (if pint fails, run `composer format` and re-check).

- [ ] **Step 7: Commit**

```bash
git add src/ResultException.php tests/Fixtures/TestError.php tests/ResultExceptionTest.php
git commit -m "feat: add ResultException with value rendering and throwable chaining"
```

---

### Task 3: Result base with Success and Failure variants

**Files:**
- Create: `src/Result.php`
- Create: `src/Success.php`
- Create: `src/Failure.php`
- Test: `tests/VariantsTest.php`

**Interfaces:**
- Consumes: `ResultException::describing()` from Task 2.
- Produces (used by every later task):
  - `Result::success(mixed $value = null): Success` — generic `@return Success<TVal>`, `Success<null>` when omitted
  - `Result::failure(mixed $error): Failure` — generic `@return Failure<TErr>`
  - `new Success($value)` / `new Failure($error)` — public constructors, private readonly payloads named `$value` / `$error`
  - `isSuccess(): bool` / `isFailure(): bool` with `@phpstan-assert-if-true/-false` narrowing to `Success<T>` / `Failure<E>`
  - `value(): T` (throws `ResultException` on Failure, native `: never` there); `error(): E` (mirrored)
  - Convention for later tasks: abstract methods on `Result` carry ALL generic PHPDoc; `Success`/`Failure` implementations carry NONE (they inherit with templates bound).

- [ ] **Step 1: Write the failing tests**

`tests/VariantsTest.php`:

```php
<?php

declare(strict_types=1);

use Iak\Result\Failure;
use Iak\Result\Result;
use Iak\Result\ResultException;
use Iak\Result\Success;
use Iak\Result\Tests\Fixtures\TestError;

it('creates a success result holding a value', function () {
    $result = Result::success(42);

    expect($result)->toBeInstanceOf(Success::class)
        ->and($result->isSuccess())->toBeTrue()
        ->and($result->isFailure())->toBeFalse()
        ->and($result->value())->toBe(42);
});

it('creates a unit success result when no value is given', function () {
    expect(Result::success()->value())->toBeNull();
});

it('creates a failure result holding an error', function () {
    $result = Result::failure(TestError::CardExpired);

    expect($result)->toBeInstanceOf(Failure::class)
        ->and($result->isFailure())->toBeTrue()
        ->and($result->isSuccess())->toBeFalse()
        ->and($result->error())->toBe(TestError::CardExpired);
});

it('can be constructed directly', function () {
    expect(new Success(1))->toEqual(Result::success(1))
        ->and(new Failure('nope'))->toEqual(Result::failure('nope'));
});

it('throws when reading the value of a failure', function () {
    $failure = Result::failure(TestError::CardExpired);

    expect(fn () => $failure->value())
        ->toThrow(ResultException::class, 'Cannot get the value of a failure result');
});

it('exposes the error on the exception when reading the value of a failure', function () {
    try {
        Result::failure(TestError::CardExpired)->value();
        $this->fail('Expected a ResultException.');
    } catch (ResultException $exception) {
        expect($exception->value)->toBe(TestError::CardExpired);
    }
});

it('chains the previous exception when the error is a throwable', function () {
    $error = new LogicException('boom');

    try {
        Result::failure($error)->value();
        $this->fail('Expected a ResultException.');
    } catch (ResultException $exception) {
        expect($exception->getPrevious())->toBe($error);
    }
});

it('throws when reading the error of a success', function () {
    expect(fn () => Result::success(42)->error())
        ->toThrow(ResultException::class, 'Cannot get the error of a success result');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/VariantsTest.php`
Expected: FAIL — `Error: Class "Iak\Result\Result" not found`.

- [ ] **Step 3: Implement the Result base**

`src/Result.php`:

```php
<?php

declare(strict_types=1);

namespace Iak\Result;

/**
 * A value that is either a {@see Success} holding a value of type T,
 * or a {@see Failure} holding an error of type E.
 *
 * @template-covariant T
 * @template-covariant E
 *
 * @immutable
 */
abstract class Result
{
    /**
     * Wrap a value in a success result. Call without arguments for a
     * result that carries no meaningful value.
     *
     * @template TVal = null
     *
     * @param  TVal  $value
     * @return Success<TVal>
     */
    public static function success(mixed $value = null): Success
    {
        return new Success($value);
    }

    /**
     * Wrap an error in a failure result.
     *
     * @template TErr
     *
     * @param  TErr  $error
     * @return Failure<TErr>
     */
    public static function failure(mixed $error): Failure
    {
        return new Failure($error);
    }

    /**
     * @phpstan-assert-if-true Success<T> $this
     * @phpstan-assert-if-false Failure<E> $this
     */
    abstract public function isSuccess(): bool;

    /**
     * @phpstan-assert-if-true Failure<E> $this
     * @phpstan-assert-if-false Success<T> $this
     */
    abstract public function isFailure(): bool;

    /**
     * The success value.
     *
     * @return T
     *
     * @throws ResultException on a failure result
     */
    abstract public function value(): mixed;

    /**
     * The error value.
     *
     * @return E
     *
     * @throws ResultException on a success result
     */
    abstract public function error(): mixed;
}
```

- [ ] **Step 4: Implement Success**

`src/Success.php`:

```php
<?php

declare(strict_types=1);

namespace Iak\Result;

/**
 * @template-covariant T
 *
 * @extends Result<T, never>
 *
 * @immutable
 */
final class Success extends Result
{
    /**
     * @param  T  $value
     */
    public function __construct(
        private readonly mixed $value,
    ) {}

    public function isSuccess(): bool
    {
        return true;
    }

    public function isFailure(): bool
    {
        return false;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function error(): never
    {
        throw ResultException::describing('Cannot get the error of a success result', $this->value);
    }
}
```

- [ ] **Step 5: Implement Failure**

`src/Failure.php`:

```php
<?php

declare(strict_types=1);

namespace Iak\Result;

/**
 * @template-covariant E
 *
 * @extends Result<never, E>
 *
 * @immutable
 */
final class Failure extends Result
{
    /**
     * @param  E  $error
     */
    public function __construct(
        private readonly mixed $error,
    ) {}

    public function isSuccess(): bool
    {
        return false;
    }

    public function isFailure(): bool
    {
        return true;
    }

    public function value(): never
    {
        throw ResultException::describing('Cannot get the value of a failure result', $this->error);
    }

    public function error(): mixed
    {
        return $this->error;
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/VariantsTest.php`
Expected: PASS (8 tests).

- [ ] **Step 7: Analyse and format**

Run: `composer analyse && vendor/bin/pint --test`
Expected: `[OK] No errors`. Troubleshooting, in the unlikely case PHPStan complains:
- "Template type TVal ... does not have a default" or a parse error on `@template TVal = null`: remove ` = null` from the template line — inference from the parameter default still yields `Success<null>`; adjust the Task 9 assertion if it prints something else.
- Variance error on the readonly payload property: confirm the property is native `readonly` (covariant templates are allowed there since PHPStan 1.10.12).

- [ ] **Step 8: Commit**

```bash
git add src/Result.php src/Success.php src/Failure.php tests/VariantsTest.php
git commit -m "feat: add Result base with sealed Success and Failure variants"
```

---

### Task 4: expect/expectError and valueOr/valueOrElse

**Files:**
- Modify: `src/Result.php` (add 4 abstract methods before the closing brace)
- Modify: `src/Success.php` (add 4 implementations before the closing brace)
- Modify: `src/Failure.php` (add 4 implementations before the closing brace)
- Test: `tests/ExtractorsTest.php`

**Interfaces:**
- Consumes: Task 3's classes; `ResultException::withMessage()` from Task 2.
- Produces: `expect(string $message): T`, `expectError(string $message): E`, `valueOr(mixed $default): T|TDefault`, `valueOrElse(callable $fallback): T|TDefault` (fallback signature `callable(E): TDefault`).

- [ ] **Step 1: Write the failing tests**

`tests/ExtractorsTest.php`:

```php
<?php

declare(strict_types=1);

use Iak\Result\Result;
use Iak\Result\ResultException;
use Iak\Result\Tests\Fixtures\TestError;

it('expect returns the value on success', function () {
    expect(Result::success(42)->expect('must have a value'))->toBe(42);
});

it('expect throws with the caller message on failure', function () {
    expect(fn () => Result::failure('nope')->expect('Order must be placeable'))
        ->toThrow(ResultException::class, 'Order must be placeable');
});

it('expectError returns the error on failure', function () {
    expect(Result::failure('nope')->expectError('must have failed'))->toBe('nope');
});

it('expectError throws with the caller message on success', function () {
    expect(fn () => Result::success(42)->expectError('must have failed'))
        ->toThrow(ResultException::class, 'must have failed');
});

it('valueOr returns the value on success', function () {
    expect(Result::success(42)->valueOr(0))->toBe(42);
});

it('valueOr returns the default on failure', function () {
    expect(Result::failure('nope')->valueOr(0))->toBe(0);
});

it('valueOrElse computes the fallback from the error on failure', function () {
    $value = Result::failure(TestError::CardExpired)
        ->valueOrElse(fn (TestError $error) => $error->name);

    expect($value)->toBe('CardExpired');
});

it('valueOrElse does not invoke the fallback on success', function () {
    $called = false;

    $value = Result::success(42)->valueOrElse(function () use (&$called) {
        $called = true;

        return 0;
    });

    expect($value)->toBe(42)->and($called)->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/ExtractorsTest.php`
Expected: FAIL — `Call to undefined method Iak\Result\Success::expect()`.

- [ ] **Step 3: Add the abstract methods**

Add to `src/Result.php` before the closing brace:

```php
    /**
     * The success value, or throw with the caller's message on failure.
     *
     * @return T
     *
     * @throws ResultException on a failure result
     */
    abstract public function expect(string $message): mixed;

    /**
     * The error value, or throw with the caller's message on success.
     *
     * @return E
     *
     * @throws ResultException on a success result
     */
    abstract public function expectError(string $message): mixed;

    /**
     * The success value, or the given default on failure.
     *
     * @template TDefault
     *
     * @param  TDefault  $default
     * @return T|TDefault
     */
    abstract public function valueOr(mixed $default): mixed;

    /**
     * The success value, or the fallback's return value on failure.
     *
     * @template TDefault
     *
     * @param  callable(E): TDefault  $fallback
     * @return T|TDefault
     */
    abstract public function valueOrElse(callable $fallback): mixed;
```

- [ ] **Step 4: Add the Success implementations**

Add to `src/Success.php` before the closing brace:

```php
    public function expect(string $message): mixed
    {
        return $this->value;
    }

    public function expectError(string $message): never
    {
        throw ResultException::withMessage($message, $this->value);
    }

    public function valueOr(mixed $default): mixed
    {
        return $this->value;
    }

    public function valueOrElse(callable $fallback): mixed
    {
        return $this->value;
    }
```

- [ ] **Step 5: Add the Failure implementations**

Add to `src/Failure.php` before the closing brace:

```php
    public function expect(string $message): never
    {
        throw ResultException::withMessage($message, $this->error);
    }

    public function expectError(string $message): mixed
    {
        return $this->error;
    }

    public function valueOr(mixed $default): mixed
    {
        return $default;
    }

    public function valueOrElse(callable $fallback): mixed
    {
        return $fallback($this->error);
    }
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/ExtractorsTest.php`
Expected: PASS (8 tests). Also run the full suite: `vendor/bin/pest` — all green.

- [ ] **Step 7: Analyse and format**

Run: `composer analyse && vendor/bin/pint --test`
Expected: `[OK] No errors`.

- [ ] **Step 8: Commit**

```bash
git add src/Result.php src/Success.php src/Failure.php tests/ExtractorsTest.php
git commit -m "feat: add expect, expectError, valueOr and valueOrElse"
```

---

### Task 5: map and mapError

**Files:**
- Modify: `src/Result.php` (add 2 abstract methods before the closing brace)
- Modify: `src/Success.php` (add 2 implementations before the closing brace)
- Modify: `src/Failure.php` (add 2 implementations before the closing brace)
- Test: `tests/CombinatorsTest.php` (create)

**Interfaces:**
- Consumes: Task 3's classes.
- Produces: `map(callable $fn): Result` (`<U>(callable(T): U): Result<U, E>`), `mapError(callable $fn): Result` (`<F>(callable(E): F): Result<T, F>`).

- [ ] **Step 1: Write the failing tests**

`tests/CombinatorsTest.php`:

```php
<?php

declare(strict_types=1);

use Iak\Result\Failure;
use Iak\Result\Result;
use Iak\Result\Success;
use Iak\Result\Tests\Fixtures\TestError;

it('map transforms the success value', function () {
    $result = Result::success(21)->map(fn (int $value) => $value * 2);

    expect($result)->toBeInstanceOf(Success::class)
        ->and($result->value())->toBe(42);
});

it('map leaves a failure untouched and does not invoke the callback', function () {
    $called = false;

    $result = Result::failure('nope')->map(function () use (&$called) {
        $called = true;

        return 1;
    });

    expect($result)->toBeInstanceOf(Failure::class)
        ->and($result->error())->toBe('nope')
        ->and($called)->toBeFalse();
});

it('mapError transforms the error value', function () {
    $result = Result::failure(TestError::CardExpired)->map(fn () => 1)
        ->mapError(fn (TestError $error) => $error->name);

    expect($result->error())->toBe('CardExpired');
});

it('mapError leaves a success untouched and does not invoke the callback', function () {
    $called = false;

    $result = Result::success(42)->mapError(function () use (&$called) {
        $called = true;

        return 'nope';
    });

    expect($result)->toBeInstanceOf(Success::class)
        ->and($result->value())->toBe(42)
        ->and($called)->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/CombinatorsTest.php`
Expected: FAIL — `Call to undefined method Iak\Result\Success::map()`.

- [ ] **Step 3: Add the abstract methods**

Add to `src/Result.php` before the closing brace:

```php
    /**
     * Transform the success value, leaving a failure untouched.
     *
     * @template U
     *
     * @param  callable(T): U  $fn
     * @return Result<U, E>
     */
    abstract public function map(callable $fn): Result;

    /**
     * Transform the error value, leaving a success untouched.
     *
     * @template F
     *
     * @param  callable(E): F  $fn
     * @return Result<T, F>
     */
    abstract public function mapError(callable $fn): Result;
```

- [ ] **Step 4: Add the Success implementations**

Add to `src/Success.php` before the closing brace:

```php
    public function map(callable $fn): Result
    {
        return new Success($fn($this->value));
    }

    public function mapError(callable $fn): Result
    {
        return $this;
    }
```

- [ ] **Step 5: Add the Failure implementations**

Add to `src/Failure.php` before the closing brace:

```php
    public function map(callable $fn): Result
    {
        return $this;
    }

    public function mapError(callable $fn): Result
    {
        return new Failure($fn($this->error));
    }
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/CombinatorsTest.php`
Expected: PASS (4 tests).

- [ ] **Step 7: Analyse and format**

Run: `composer analyse && vendor/bin/pint --test`
Expected: `[OK] No errors`.

- [ ] **Step 8: Commit**

```bash
git add src/Result.php src/Success.php src/Failure.php tests/CombinatorsTest.php
git commit -m "feat: add map and mapError"
```

---

### Task 6: chain and orElse

**Files:**
- Modify: `src/Result.php` (add 2 abstract methods before the closing brace)
- Modify: `src/Success.php` (add 2 implementations before the closing brace)
- Modify: `src/Failure.php` (add 2 implementations before the closing brace)
- Test: `tests/CombinatorsTest.php` (append)

**Interfaces:**
- Consumes: Task 3's classes.
- Produces: `chain(callable $fn): Result` (`<U, F>(callable(T): Result<U, F>): Result<U, E|F>`), `orElse(callable $fn): Result` (`<U, F>(callable(E): Result<U, F>): Result<T|U, F>`).

- [ ] **Step 1: Append the failing tests**

Append to `tests/CombinatorsTest.php`:

```php
it('chain pipes the success value into the next result', function () {
    $result = Result::success(21)->chain(fn (int $value) => Result::success($value * 2));

    expect($result->value())->toBe(42);
});

it('chain returns the failure produced by the callback', function () {
    $result = Result::success(21)->chain(fn () => Result::failure('nope'));

    expect($result->isFailure())->toBeTrue()
        ->and($result->error())->toBe('nope');
});

it('chain short-circuits on failure without invoking the callback', function () {
    $called = false;

    $result = Result::failure('nope')->chain(function () use (&$called) {
        $called = true;

        return Result::success(1);
    });

    expect($result->isFailure())->toBeTrue()
        ->and($result->error())->toBe('nope')
        ->and($called)->toBeFalse();
});

it('orElse recovers a failure into the next result', function () {
    $result = Result::failure('nope')->orElse(fn (string $error) => Result::success(strlen($error)));

    expect($result->value())->toBe(4);
});

it('orElse can produce a new failure', function () {
    $result = Result::failure('nope')->orElse(fn () => Result::failure(TestError::CardExpired));

    expect($result->error())->toBe(TestError::CardExpired);
});

it('orElse passes a success through without invoking the callback', function () {
    $called = false;

    $result = Result::success(42)->orElse(function () use (&$called) {
        $called = true;

        return Result::failure('nope');
    });

    expect($result->value())->toBe(42)->and($called)->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/CombinatorsTest.php`
Expected: FAIL — `Call to undefined method Iak\Result\Success::chain()`.

- [ ] **Step 3: Add the abstract methods**

Add to `src/Result.php` before the closing brace:

```php
    /**
     * Chain another result-returning operation on the success value; a
     * failure short-circuits past it. Error types accumulate as a union.
     *
     * @template U
     * @template F
     *
     * @param  callable(T): Result<U, F>  $fn
     * @return Result<U, E|F>
     */
    abstract public function chain(callable $fn): Result;

    /**
     * Recover from a failure with another result-returning operation; a
     * success passes through untouched.
     *
     * @template U
     * @template F
     *
     * @param  callable(E): Result<U, F>  $fn
     * @return Result<T|U, F>
     */
    abstract public function orElse(callable $fn): Result;
```

- [ ] **Step 4: Add the Success implementations**

Add to `src/Success.php` before the closing brace:

```php
    public function chain(callable $fn): Result
    {
        return $fn($this->value);
    }

    public function orElse(callable $fn): Result
    {
        return $this;
    }
```

- [ ] **Step 5: Add the Failure implementations**

Add to `src/Failure.php` before the closing brace:

```php
    public function chain(callable $fn): Result
    {
        return $this;
    }

    public function orElse(callable $fn): Result
    {
        return $fn($this->error);
    }
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/CombinatorsTest.php`
Expected: PASS (10 tests).

- [ ] **Step 7: Analyse and format**

Run: `composer analyse && vendor/bin/pint --test`
Expected: `[OK] No errors`.

- [ ] **Step 8: Commit**

```bash
git add src/Result.php src/Success.php src/Failure.php tests/CombinatorsTest.php
git commit -m "feat: add chain and orElse"
```

---

### Task 7: match

**Files:**
- Modify: `src/Result.php` (add 1 abstract method before the closing brace)
- Modify: `src/Success.php` (add 1 implementation before the closing brace)
- Modify: `src/Failure.php` (add 1 implementation before the closing brace)
- Test: `tests/MatchTest.php`

**Interfaces:**
- Consumes: Task 3's classes.
- Produces: `match(callable $success, callable $failure): mixed` (`<A, B>(callable(T): A, callable(E): B): A|B`). Parameter names `$success`/`$failure` are part of the API (named arguments). Note: `match` is a reserved keyword but legal as a method name in PHP 8.

- [ ] **Step 1: Write the failing tests**

`tests/MatchTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/pest tests/MatchTest.php`
Expected: FAIL — `Call to undefined method Iak\Result\Success::match()`.

- [ ] **Step 3: Add the abstract method**

Add to `src/Result.php` before the closing brace:

```php
    /**
     * Handle both variants and return the outcome of the matching arm.
     *
     * @template TSuccessReturn
     * @template TFailureReturn
     *
     * @param  callable(T): TSuccessReturn  $success
     * @param  callable(E): TFailureReturn  $failure
     * @return TSuccessReturn|TFailureReturn
     */
    abstract public function match(callable $success, callable $failure): mixed;
```

- [ ] **Step 4: Add the Success implementation**

Add to `src/Success.php` before the closing brace:

```php
    public function match(callable $success, callable $failure): mixed
    {
        return $success($this->value);
    }
```

- [ ] **Step 5: Add the Failure implementation**

Add to `src/Failure.php` before the closing brace:

```php
    public function match(callable $success, callable $failure): mixed
    {
        return $failure($this->error);
    }
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/pest tests/MatchTest.php`
Expected: PASS (3 tests).

- [ ] **Step 7: Analyse and format**

Run: `composer analyse && vendor/bin/pint --test`
Expected: `[OK] No errors`.

- [ ] **Step 8: Commit**

```bash
git add src/Result.php src/Success.php src/Failure.php tests/MatchTest.php
git commit -m "feat: add match"
```

---

### Task 8: Object semantics and architecture tests

**Files:**
- Test: `tests/ObjectSemanticsTest.php`
- Test: `tests/ArchTest.php`

**Interfaces:**
- Consumes: the complete API from Tasks 3–7.
- Produces: no new API — pins equality, serialization, finality, and strict types as tested guarantees.

- [ ] **Step 1: Write the object-semantics tests**

`tests/ObjectSemanticsTest.php`:

```php
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
```

- [ ] **Step 2: Write the architecture tests**

`tests/ArchTest.php`:

```php
<?php

declare(strict_types=1);

arch('all package files use strict types')
    ->expect('Iak\Result')
    ->toUseStrictTypes();

arch('the variants and the exception are final')
    ->expect([Iak\Result\Success::class, Iak\Result\Failure::class, Iak\Result\ResultException::class])
    ->toBeFinal();

arch('the package has no framework dependencies')
    ->expect('Iak\Result')
    ->not->toUse(['Illuminate', 'Symfony']);
```

- [ ] **Step 3: Run the full suite**

Run: `vendor/bin/pest`
Expected: PASS — every test from Tasks 2–8 green.

- [ ] **Step 4: Analyse and format**

Run: `composer analyse && vendor/bin/pint --test`
Expected: `[OK] No errors`.

- [ ] **Step 5: Commit**

```bash
git add tests/ObjectSemanticsTest.php tests/ArchTest.php
git commit -m "test: pin equality, serialization and architecture guarantees"
```

---

### Task 9: Type-inference fixtures

**Files:**
- Create: `types/results.php`
- Modify: `phpstan.neon` (add `types` to paths)

**Interfaces:**
- Consumes: the complete API from Tasks 3–7.
- Produces: CI-enforced type-inference guarantees. `composer analyse` now fails if any generic signature regresses.

- [ ] **Step 1: Write the fixture file**

`types/results.php`. IMPORTANT for the implementer: the expected-type strings must match PHPStan's exact rendering. If an assertion fails, PHPStan's error message prints the ACTUAL inferred type — when the actual type is semantically equivalent (union member order, enum case literal like `Failure<...PaymentError::CardExpired>` instead of `Failure<...PaymentError>`, `*NEVER*` vs `never`), update the assertion string to match. When the actual type is semantically WRONG (`mixed` where a specific type was expected, missing narrowing, a variant not assignable in `covariance()`), fix the src annotations instead — that is the regression this file exists to catch.

```php
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

final class Receipt
{
}

/**
 * @return Result<Receipt, PaymentError>
 */
function charge(bool $succeeds): Result
{
    return $succeeds
        ? Result::success(new Receipt())
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
 * @param Result<Receipt, PaymentError> $result
 */
function acceptsResult(Result $result): void
{
}

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
        'Iak\Result\Result<int|Iak\Result\Types\Receipt, string>',
        $result->orElse(fn (PaymentError $error): Result => nextStep(new Receipt())),
    );

    assertType('int|Iak\Result\Types\Receipt', $result->valueOr(1));

    assertType(
        'Iak\Result\Types\Receipt|string',
        $result->valueOrElse(fn (PaymentError $error): string => $error->name),
    );

    assertType(
        'int|string',
        $result->match(
            success: fn (Receipt $receipt): int => 1,
            failure: fn (PaymentError $error): string => $error->name,
        ),
    );
}

function covariance(): void
{
    acceptsResult(Result::success(new Receipt()));
    acceptsResult(Result::failure(PaymentError::CardExpired));
    acceptsResult(new Success(new Receipt()));
    acceptsResult(new Failure(PaymentError::InsufficientFunds));
}

function neverOnWrongVariant(): void
{
    $failure = Result::failure(PaymentError::CardExpired);

    assertType('*NEVER*', $failure->value());
}
```

- [ ] **Step 2: Add types/ to the PHPStan paths**

Replace the contents of `phpstan.neon` with:

```neon
parameters:
    level: 9
    paths:
        - src
        - types
```

- [ ] **Step 3: Run the analysis**

Run: `composer analyse`
Expected: `[OK] No errors`. If assertions fail, follow the Step 1 guidance: adjust rendering-only mismatches in the fixture; fix `src/` annotations for semantic mismatches. The `covariance()` function must produce no errors — if it does, the `@template-covariant`/`@extends Result<T, never>` annotations are broken; do not "fix" it by widening parameter types.

- [ ] **Step 4: Run the full test suite (unchanged but confirm)**

Run: `vendor/bin/pest`
Expected: PASS — types/ is never autoloaded at runtime, nothing changes.

- [ ] **Step 5: Commit**

```bash
git add types/results.php phpstan.neon
git commit -m "test: add PHPStan type-inference fixtures"
```

---

### Task 10: README

**Files:**
- Create: `README.md`

**Interfaces:**
- Consumes: the complete, verified API.
- Produces: user-facing documentation including the iak/action interop section and the idempotency caveat required by the spec.

- [ ] **Step 1: Write README.md**

````markdown
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

- **Immutable** — every transformation returns a new instance.
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
````

- [ ] **Step 2: Verify the README code samples against the real API**

Check each method name used in the README against `src/Result.php` (`success`, `failure`, `isSuccess`, `isFailure`, `value`, `error`, `valueOr`, `valueOrElse`, `expect`, `expectError`, `map`, `mapError`, `chain`, `orElse`, `match`). No Rust vocabulary may appear.

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: add README with iak/action interop guide"
```

---

### Task 11: CI workflow

**Files:**
- Create: `.github/workflows/tests.yml`

**Interfaces:**
- Consumes: composer scripts and the green suite from all prior tasks.
- Produces: CI running tests + analysis on PHP 8.2/8.3/8.4.

- [ ] **Step 1: Write the workflow**

`.github/workflows/tests.yml`:

```yaml
name: Tests

on:
  push:
    branches: [main]
  pull_request:

jobs:
  tests:
    runs-on: ubuntu-latest
    strategy:
      fail-fast: false
      matrix:
        php: ['8.2', '8.3', '8.4']
    name: PHP ${{ matrix.php }}
    steps:
      - uses: actions/checkout@v4

      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none

      - run: composer install --prefer-dist --no-interaction

      - run: vendor/bin/pest

      - run: vendor/bin/phpstan analyse --no-progress
```

- [ ] **Step 2: Validate the YAML parses**

Run: `php -r 'echo function_exists("yaml_parse") ? "skip" : "no ext-yaml, visual check only", PHP_EOL;'` — if ext-yaml is unavailable, visually confirm indentation matches the block above exactly.

- [ ] **Step 3: Run the full local gate one last time**

Run: `vendor/bin/pest && composer analyse && vendor/bin/pint --test`
Expected: all three pass.

- [ ] **Step 4: Commit**

```bash
git add .github/workflows/tests.yml
git commit -m "ci: run tests and static analysis on PHP 8.2-8.4"
```
