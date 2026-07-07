<?php

declare(strict_types=1);
use Iak\Result\Failure;
use Iak\Result\ResultException;
use Iak\Result\Success;

arch('all package files use strict types')
    ->expect('Iak\Result')
    ->toUseStrictTypes();

arch('the variants and the exception are final')
    ->expect([Success::class, Failure::class, ResultException::class])
    ->toBeFinal();

arch('the package has no framework dependencies')
    ->expect('Iak\Result')
    ->not->toUse(['Illuminate', 'Symfony']);
