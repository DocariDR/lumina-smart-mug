<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

spl_autoload_register(static function (string $class): void {
    $prefix = 'Lumina\\';

    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

function redirect_home(string $status): never
{
    header('Location: /?newsletter=' . rawurlencode($status) . '#hero', true, 303);
    exit;
}