<?php
declare(strict_types=1);
namespace Lumina\Subscription;

enum UnsubscribeOutcome: string
{
    case Unsubscribed = 'unsubscribed';
    case TokenInvalid = 'token-invalid';
    case Error = 'error';
}