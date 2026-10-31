<?php
declare(strict_types=1);
namespace Lumina\Order;
interface OrderStore
{
    /**
     * Crée une commande en attente. Si une commande identique (même e-mail et même quantité)
     * est encore en attente depuis moins de 10 minutes, elle est passée à « cancelled ».
     * Les deux opérations sont faites dans une même transaction.
     * @param array<string, mixed> $order
     */
    public function createPending(array $order): void;
}