<?php

declare(strict_types=1);

namespace Lumina\Subscription;

use Lumina\Database\DuplicateKeyException;
use PDO;
use PDOException;

final class SubscriberRepository implements SubscriberStore
{
    /** Code SQLSTATE de violation de contrainte d'intégrité. */
    private const SQLSTATE_INTEGRITY = '23000';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, email, status, confirm_token_expires_at FROM subscribers WHERE email = :email LIMIT 1'
        );
        $statement->execute(['email' => $email]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByTokenHash(string $tokenHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, email, status, confirm_token_expires_at FROM subscribers WHERE confirm_token_hash = :hash LIMIT 1'
        );
        $statement->execute(['hash' => $tokenHash]);
        $row = $statement->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function createPending(
        string $email,
        string $tokenHash,
        string $tokenExpiresAt,
        string $consentAt,
        string $consentVersion,
        string $ipHash,
    ): void {
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO subscribers
                    (email, status, confirm_token_hash, confirm_token_expires_at, consent_at, consent_version, ip_hash)
                 VALUES
                    (:email, \'pending\', :hash, :expires, :consent_at, :version, :ip_hash)'
            );
            $statement->execute([
                'email' => $email,
                'hash' => $tokenHash,
                'expires' => $tokenExpiresAt,
                'consent_at' => $consentAt,
                'version' => $consentVersion,
                'ip_hash' => $ipHash,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === self::SQLSTATE_INTEGRITY) {
                throw new DuplicateKeyException('Adresse déjà enregistrée.', 0, $exception);
            }

            throw $exception;
        }
    }

    public function resetToPending(
        int $id,
        string $tokenHash,
        string $tokenExpiresAt,
        string $consentAt,
        string $consentVersion,
        string $ipHash,
    ): void {
        $statement = $this->pdo->prepare(
            'UPDATE subscribers
                SET status = \'pending\',
                    confirm_token_hash = :hash,
                    confirm_token_expires_at = :expires,
                    consent_at = :consent_at,
                    consent_version = :version,
                    ip_hash = :ip_hash
              WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'hash' => $tokenHash,
            'expires' => $tokenExpiresAt,
            'consent_at' => $consentAt,
            'version' => $consentVersion,
            'ip_hash' => $ipHash,
        ]);
    }

    public function markConfirmed(int $id, string $confirmedAt): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE subscribers
                SET status = \'confirmed\',
                    confirmed_at = :confirmed_at,
                    confirm_token_hash = NULL,
                    confirm_token_expires_at = NULL
              WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'confirmed_at' => $confirmedAt]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'status' => (string) $row['status'],
            'confirm_token_expires_at' => $row['confirm_token_expires_at'] === null
                ? null
                : (string) $row['confirm_token_expires_at'],
        ];
    }
}