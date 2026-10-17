<?php

declare(strict_types=1);

namespace Lumina\Subscription;

enum ConfirmOutcome: string
{
    case Confirmed = 'confirmed';
    case TokenInvalid = 'token-invalid';
}