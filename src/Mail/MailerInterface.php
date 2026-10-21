<?php
declare(strict_types=1);

namespace Lumina\Mail;

interface MailerInterface
{
    /** @param array<string, string> $headers */
    public function send(string $to, string $subject, string $textBody, array $headers = [], ?string $htmlBody = null): void;
}