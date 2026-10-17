<?php

declare(strict_types=1);

namespace Lumina\Database;

use RuntimeException;

/** Levée quand une insertion viole une contrainte d'unicité. */
final class DuplicateKeyException extends RuntimeException
{
}