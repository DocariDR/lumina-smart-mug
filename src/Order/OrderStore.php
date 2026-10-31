<?php
declare(strict_types=1);
namespace Lumina\Order;
interface OrderStore
{
    /** @param array<string, mixed> $order */
    public function createPending(array $order): void;
}