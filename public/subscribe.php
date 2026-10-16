<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Lumina\Subscription\EmailValidator;

$rawEmail = $_POST['email'] ?? '';
$email = is_string($rawEmail) ? EmailValidator::normalize($rawEmail) : '';

if (!EmailValidator::isValid($email)) {
    redirect_home('invalid');
}

redirect_home('unavailable');