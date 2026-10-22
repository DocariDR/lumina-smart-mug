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
    public const CONSENT_VERSION = 'newsletter-v2';
    public const TOKEN_TTL_HOURS = 48;

    public const TOKEN_PATTERN = '/^[a-f0-9]{64}$/';
    public function __construct(private readonly SubscriberStore $store, private readonly MailerInterface $mailer, private readonly string $appUrl, private readonly string $ipSalt) {}
    public function subscribe(string $rawEmail, bool $consent, string $ip): SubscribeOutcome
    {
        $email = EmailValidator::normalize($rawEmail);
        if (!EmailValidator::isValid($email)) return SubscribeOutcome::Invalid;
        if (!$consent) return SubscribeOutcome::ConsentRequired;
        $now = $this->now();
        $token = bin2hex(random_bytes(32));
        $unsubscribeToken = bin2hex(random_bytes(32));
        $tokenHash = self::hashToken($token);
        $unsubscribeTokenHash = self::hashToken($unsubscribeToken);
        $expiresAt = $now->modify('+' . self::TOKEN_TTL_HOURS . ' hours')->format('Y-m-d H:i:s');
        try {
            $existing = $this->store->findByEmail($email);

            if ($existing === null) {
                $this->store->createPending($email, $tokenHash, $expiresAt, $unsubscribeTokenHash, $now->format('Y-m-d H:i:s'), self::CONSENT_VERSION, $this->hashIp($ip));
            } elseif ($existing['status'] === 'confirmed') {
                return SubscribeOutcome::CheckEmail;
            } else {
                $this->store->resetToPending($existing['id'], $tokenHash, $expiresAt, $unsubscribeTokenHash, $now->format('Y-m-d H:i:s'), self::CONSENT_VERSION, $this->hashIp($ip));
            }
        } catch (DuplicateKeyException) {
            return SubscribeOutcome::CheckEmail;
        }

        try {
            $unsubscribeLink = $this->unsubscribeLink($unsubscribeToken);
            $confirmLink = rtrim($this->appUrl, '/') . '/confirm.php?token=' . $token;
            $this->mailer->send(
                $email,
                'Confirm your Lumina subscription',
                $this->confirmationBody($token, $unsubscribeLink),
                [
                    'List-Unsubscribe' => '<' . $unsubscribeLink . '>',
                    'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                ],
                $this->confirmationHtml($confirmLink, $unsubscribeLink),
            );
        } catch (RuntimeException) {
            return SubscribeOutcome::Error;
        }

        return SubscribeOutcome::CheckEmail;
    }

    public function confirm(string $token): ConfirmOutcome
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) return ConfirmOutcome::TokenInvalid;
        $subscriber = $this->store->findByTokenHash(self::hashToken($token));
        if ($subscriber === null || $subscriber['status'] !== 'pending') return ConfirmOutcome::TokenInvalid;
        $expiresAt = $subscriber['confirm_token_expires_at'];
        if ($expiresAt === null || $expiresAt < $this->now()->format('Y-m-d H:i:s')) return ConfirmOutcome::TokenInvalid;
        $this->store->markConfirmed($subscriber['id'], $this->now()->format('Y-m-d H:i:s'));

        return ConfirmOutcome::Confirmed;
    }

    public function unsubscribe(string $token): UnsubscribeOutcome
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) return UnsubscribeOutcome::TokenInvalid;
        $subscriber = $this->store->findByUnsubscribeTokenHash(self::hashToken($token));
        if ($subscriber === null || $subscriber['status'] === 'unsubscribed') return UnsubscribeOutcome::TokenInvalid;
        $this->store->markUnsubscribed($subscriber['id'], $this->now()->format('Y-m-d H:i:s'));

        return UnsubscribeOutcome::Unsubscribed;
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, $this->ipSalt);
    }

    private function unsubscribeLink(string $token): string
    {
        return rtrim($this->appUrl, '/') . '/unsubscribe.php?token=' . $token;
    }

    private function confirmationBody(string $token, string $unsubscribeLink): string
    {
        $link = rtrim($this->appUrl, '/') . '/confirm.php?token=' . $token;
        return "Thanks for signing up for the Lumina newsletter.\n\nConfirm your subscription with this link (valid for " . self::TOKEN_TTL_HOURS . " hours):\n" . $link . "\n\nYou can unsubscribe at any time here:\n" . $unsubscribeLink . "\n\nIf you did not request this, you can ignore this email.";
    }
    private function confirmationHtml(string $confirmLink, string $unsubscribeLink): string
    {
        $confirm = htmlspecialchars($confirmLink, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $unsubscribe = htmlspecialchars($unsubscribeLink, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $hours = self::TOKEN_TTL_HOURS;

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm your Lumina subscription</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background-color:#ffffff;border-radius:8px;font-family:'Segoe UI',Tahoma,Geneva,Verdana,Arial,sans-serif;color:#222222;">
                <tr>
                    <td style="padding:24px 32px;border-top:4px solid #a04000;border-radius:8px 8px 0 0;">
                        <p style="margin:0;font-size:18px;font-weight:bold;color:#a04000;">Lumina Smart Mug</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 32px 32px 32px;">
                        <h1 style="margin:0 0 16px 0;font-size:22px;">Confirm your subscription</h1>
                        <p style="margin:0 0 24px 0;font-size:16px;line-height:1.5;">Thanks for signing up for the Lumina newsletter. Click the button below to confirm your email address. The link is valid for {$hours} hours.</p>
                        <table role="presentation" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="center" style="border-radius:4px;background-color:#222222;">
                                    <a href="{$confirm}" style="display:inline-block;padding:12px 24px;font-size:16px;color:#ffffff;text-decoration:none;border-radius:4px;">Confirm subscription</a>
                                </td>
                            </tr>
                        </table>
                        <p style="margin:24px 0 0 0;font-size:13px;line-height:1.5;color:#555555;">If the button does not work, copy this link into your browser:<br><a href="{$confirm}" style="color:#1a5fb4;word-break:break-all;">{$confirm}</a></p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 32px;background-color:#fafafa;border-radius:0 0 8px 8px;font-size:12px;line-height:1.5;color:#777777;">
                        <p style="margin:0 0 8px 0;">If you did not request this, you can ignore this email.</p>
                        <p style="margin:0;">You can <a href="{$unsubscribe}" style="color:#777777;">unsubscribe</a> at any time.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
HTML;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}