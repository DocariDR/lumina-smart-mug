<?php
declare(strict_types=1);

namespace Lumina\Mail;

use RuntimeException;

final class OutboxMailer implements MailerInterface
{
    public function __construct(private readonly string $directory)
    {
    }
    public function send(string $to, string $subject, string $textBody, array $headers = [], ?string $htmlBody = null): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Dossier outbox introuvable.');
        }
        $headerText = '';
        foreach ($headers as $name => $value) {
            $headerText .= $name . ': ' . $value . "\n";
        }
        $content = "To: {$to}\nSubject: {$subject}\n" . $headerText . "\n{$textBody}\n";
        $file = $this->directory . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.txt';
        if (file_put_contents($file, $content) === false) {
            throw new RuntimeException('Écriture de l\'email impossible.');
        }
    }
}