<?php
declare(strict_types=1);
namespace Lumina\Order;
use InvalidArgumentException;
/**
 * Saisie invalide : porte la liste des champs fautifs pour que la page
 * puisse les signaler un par un. Les messages sont affichés par order.php.
 */
final class OrderValidationException extends InvalidArgumentException
{
    /** @param list<string> $fields */
    public function __construct(public readonly array $fields)
    {
        parent::__construct('Saisie invalide : ' . implode(', ', $fields));
    }
}