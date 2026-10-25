<?php
declare(strict_types=1);
namespace Lumina\Order;
use RuntimeException;
/** Une commande identique (même e-mail, même quantité) est déjà en attente depuis peu. */
final class OrderDuplicateException extends RuntimeException
{
}