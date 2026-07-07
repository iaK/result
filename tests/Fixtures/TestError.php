<?php

declare(strict_types=1);

namespace Iak\Result\Tests\Fixtures;

enum TestError
{
    case CardExpired;
    case InsufficientFunds;
}
