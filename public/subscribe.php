<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

use Lumina\Subscription\EmailValidator;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

$rawEmail = $_POST['email'] ?? '';
$email = is_string($rawEmail) ? EmailValidator::normalize($rawEmail) : '';

if (!EmailValidator::isValid($email)) {
    redirect_home('invalid');
}

// L'enregistrement en base et le double opt-in arrivent en V2.
redirect_home('unavailable');