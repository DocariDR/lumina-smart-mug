<?php

declare(strict_types=1);

namespace Lumina\Subscription;

use DateTimeImmutable;
use DateTimeZone;
use Lumina\Database\DuplicateKeyException;
use Lumina\Mail\MailerInterface;
use RuntimeException;

final class SubscriptionService
{
    public const CONSENT_VERSION = 'newsletter-v1';
    public const TOKEN_TTL_HOURS = 48;

    private const TOKEN_PATTERN = '/^[a-f0-9]{64}$/';

    public function __construct(
        private readonly SubscriberStore $store,
        private readonly MailerInterface $mailer,
        private readonly string $appUrl,
        private readonly string $ipSalt,
    ) {
    }

    public function subscribe(string $rawEmail, bool $consent, string $ip): SubscribeOutcome
    {
        $email = EmailValidator::normalize($rawEmail);

        if (!EmailValidator::isValid($email)) {
            return SubscribeOutcome::Invalid;
        }

        if (!$consent) {
            return SubscribeOutcome::ConsentRequired;
        }

        $now = $this->now();
        $token = bin2hex(random_bytes(32));
        $tokenHash = self::hashToken($token);
        $expiresAt = $now->modify('+' . self::TOKEN_TTL_HOURS . ' hours')->format('Y-m-d H:i:s');
        $consentAt = $now->format('Y-m-d H:i:s');
        $ipHash = $this->hashIp($ip);

        try {
            $existing = $this->store->findByEmail($email);

            if ($existing === null) {
                $this->store->createPending($email, $tokenHash, $expiresAt, $consentAt, self::CONSENT_VERSION, $ipHash);
            } elseif ($existing['status'] === 'confirmed') {
                // Réponse identique pour ne pas révéler qu'une adresse est déjà inscrite.
                return SubscribeOutcome::CheckEmail;
            } else {
                // En attente ou désinscrite : nouveau jeton et nouveau consentement.
                $this->store->resetToPending($existing['id'], $tokenHash, $expiresAt, $consentAt, self::CONSENT_VERSION, $ipHash);
            }
        } catch (DuplicateKeyException) {
            // Inscription concurrente avec la même adresse : la première l'emporte.
            return SubscribeOutcome::CheckEmail;
        }

        try {
            $this->mailer->send(
                $email,
                'Confirm your Lumina subscription',
                $this->confirmationBody($token),
            );
        } catch (RuntimeException) {
            return SubscribeOutcome::Error;
        }

        return SubscribeOutcome::CheckEmail;
    }

    public function confirm(string $token): ConfirmOutcome
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return ConfirmOutcome::TokenInvalid;
        }

        $subscriber = $this->store->findByTokenHash(self::hashToken($token));

        if ($subscriber === null || $subscriber['status'] !== 'pending') {
            return ConfirmOutcome::TokenInvalid;
        }

        $expiresAt = $subscriber['confirm_token_expires_at'];
        if ($expiresAt === null || $expiresAt < $this->now()->format('Y-m-d H:i:s')) {
            return ConfirmOutcome::TokenInvalid;
        }

        $this->store->markConfirmed($subscriber['id'], $this->now()->format('Y-m-d H:i:s'));

        return ConfirmOutcome::Confirmed;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, $this->ipSalt);
    }

    private function confirmationBody(string $token): string
    {
        $link = rtrim($this->appUrl, '/') . '/confirm.php?token=' . $token;

        return "Thanks for signing up for the Lumina newsletter.\n\n"
            . "Confirm your subscription with this link (valid for " . self::TOKEN_TTL_HOURS . " hours):\n"
            . $link . "\n\n"
            . "If you did not request this, you can ignore this email.";
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}