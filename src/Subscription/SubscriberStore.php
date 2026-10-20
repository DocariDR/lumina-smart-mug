<?php

declare(strict_types=1);

namespace Lumina\Subscription;

use Lumina\Database\DuplicateKeyException;

/**
 * Accès aux abonnés.
 *
 * Les dates sont transmises en UTC au format 'Y-m-d H:i:s'.
 *
 * @phpstan-type Subscriber array{
 *     id: int,
 *     email: string,
 *     status: string,
 *     confirm_token_expires_at: ?string
 * }
 */
interface SubscriberStore
{
    /** @return Subscriber|null */
    public function findByEmail(string $email): ?array;

    /** @return Subscriber|null */
    public function findByTokenHash(string $tokenHash): ?array;

    /** @return Subscriber|null */
    public function findByUnsubscribeTokenHash(string $tokenHash): ?array;

    /**
     * Crée un abonné en attente de confirmation.
     *
     * @throws DuplicateKeyException si l'adresse existe déjà.
     */
    public function createPending(
        string $email,
        string $tokenHash,
        string $tokenExpiresAt,
        string $unsubscribeTokenHash,
        string $consentAt,
        string $consentVersion,
        string $ipHash,
    ): void;

    /**
     * Remet une adresse en attente avec un nouveau jeton
     * de confirmation, un jeton de désabonnement et un nouveau consentement.
     */
    public function resetToPending(
        int $id,
        string $tokenHash,
        string $tokenExpiresAt,
        string $unsubscribeTokenHash,
        string $consentAt,
        string $consentVersion,
        string $ipHash,
    ): void;

    public function markConfirmed(int $id, string $confirmedAt): void;

    public function markUnsubscribed(int $id, string $unsubscribedAt): void;
}