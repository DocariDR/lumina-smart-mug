<?php

declare(strict_types=1);

namespace Lumina\Subscription;

enum SubscribeOutcome: string
{
    case Invalid = 'invalid';
    case ConsentRequired = 'consent';
    case CheckEmail = 'check-email';
    case Error = 'error';
}