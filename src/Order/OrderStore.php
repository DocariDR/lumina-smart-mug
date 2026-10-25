<?php
declare(strict_types=1);
namespace Lumina\Order;
interface OrderStore
{
    /** @param array<string, mixed> $order */
    public function createPending(array $order): void;

    /** Vrai si une commande en attente existe déjà pour cet e-mail et cette quantité, créée il y a peu. */
    public function hasRecentPending(string $email, int $quantity): bool;
}