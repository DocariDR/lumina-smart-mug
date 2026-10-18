<?php

declare(strict_types=1);

namespace Lumina\Mail;

interface MailerInterface
{
    /** @throws \RuntimeException si l'envoi échoue */
    public function send(string $to, string $subject, string $textBody): void;
}