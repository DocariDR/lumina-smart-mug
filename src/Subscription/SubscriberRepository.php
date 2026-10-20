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

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * Recherche un abonné par son adresse e-mail.
     *
     * @return array{
     *     id: int,
     *     email: string,
     *     status: string,
     *     confirm_token_expires_at: ?string
     * }|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->fetchOne(
            <<<'SQL'
                SELECT
                    id,
                    email,
                    status,
                    confirm_token_expires_at
                FROM subscribers
                WHERE email = :value
                LIMIT 1
                SQL,
            'value',
            $email,
        );
    }

    /**
     * Recherche un abonné par le hash de son jeton de confirmation.
     *
     * @return array{
     *     id: int,
     *     email: string,
     *     status: string,
     *     confirm_token_expires_at: ?string
     * }|null
     */
    public function findByTokenHash(string $tokenHash): ?array
    {
        return $this->fetchOne(
            <<<'SQL'
                SELECT
                    id,
                    email,
                    status,
                    confirm_token_expires_at
                FROM subscribers
                WHERE confirm_token_hash = :value
                LIMIT 1
                SQL,
            'value',
            $tokenHash,
        );
    }

    /**
     * Recherche un abonné par le hash de son jeton de désabonnement.
     *
     * @return array{
     *     id: int,
     *     email: string,
     *     status: string,
     *     confirm_token_expires_at: ?string
     * }|null
     */
    public function findByUnsubscribeTokenHash(
        string $tokenHash,
    ): ?array {
        return $this->fetchOne(
            <<<'SQL'
                SELECT
                    id,
                    email,
                    status,
                    confirm_token_expires_at
                FROM subscribers
                WHERE unsubscribe_token_hash = :value
                LIMIT 1
                SQL,
            'value',
            $tokenHash,
        );
    }

    /**
     * Crée un nouvel abonné en attente de confirmation.
     *
     * @throws DuplicateKeyException si une contrainte d'intégrité
     *                                est violée.
     */
    public function createPending(
        string $email,
        string $tokenHash,
        string $tokenExpiresAt,
        string $unsubscribeTokenHash,
        string $consentAt,
        string $consentVersion,
        string $ipHash,
    ): void {
        try {
            $statement = $this->pdo->prepare(
                <<<'SQL'
                    INSERT INTO subscribers (
                        email,
                        status,
                        confirm_token_hash,
                        confirm_token_expires_at,
                        unsubscribe_token_hash,
                        consent_at,
                        consent_version,
                        ip_hash
                    )
                    VALUES (
                        :email,
                        'pending',
                        :hash,
                        :expires,
                        :unsubscribe_hash,
                        :consent_at,
                        :version,
                        :ip_hash
                    )
                    SQL,
            );

            $statement->execute([
                'email' => $email,
                'hash' => $tokenHash,
                'expires' => $tokenExpiresAt,
                'unsubscribe_hash' => $unsubscribeTokenHash,
                'consent_at' => $consentAt,
                'version' => $consentVersion,
                'ip_hash' => $ipHash,
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === self::SQLSTATE_INTEGRITY) {
                throw new DuplicateKeyException(
                    'Adresse déjà enregistrée.',
                    0,
                    $exception,
                );
            }

            throw $exception;
        }
    }

    /**
     * Remet un abonné en attente de confirmation.
     *
     * Renouvelle les jetons et les informations de consentement.
     * Efface la date de désabonnement précédente.
     */
    public function resetToPending(
        int $id,
        string $tokenHash,
        string $tokenExpiresAt,
        string $unsubscribeTokenHash,
        string $consentAt,
        string $consentVersion,
        string $ipHash,
    ): void {
        $statement = $this->pdo->prepare(
            <<<'SQL'
                UPDATE subscribers
                SET
                    status = 'pending',
                    confirm_token_hash = :hash,
                    confirm_token_expires_at = :expires,
                    unsubscribe_token_hash = :unsubscribe_hash,
                    consent_at = :consent_at,
                    consent_version = :version,
                    ip_hash = :ip_hash,
                    unsubscribed_at = NULL
                WHERE id = :id
                SQL,
        );

        $statement->execute([
            'id' => $id,
            'hash' => $tokenHash,
            'expires' => $tokenExpiresAt,
            'unsubscribe_hash' => $unsubscribeTokenHash,
            'consent_at' => $consentAt,
            'version' => $consentVersion,
            'ip_hash' => $ipHash,
        ]);
    }

    /**
     * Confirme l'abonnement et invalide le jeton de confirmation.
     */
    public function markConfirmed(
        int $id,
        string $confirmedAt,
    ): void {
        $statement = $this->pdo->prepare(
            <<<'SQL'
                UPDATE subscribers
                SET
                    status = 'confirmed',
                    confirmed_at = :confirmed_at,
                    confirm_token_hash = NULL,
                    confirm_token_expires_at = NULL
                WHERE id = :id
                SQL,
        );

        $statement->execute([
            'id' => $id,
            'confirmed_at' => $confirmedAt,
        ]);
    }

    /**
     * Marque un abonné comme désabonné.
     */
    public function markUnsubscribed(
        int $id,
        string $unsubscribedAt,
    ): void {
        $statement = $this->pdo->prepare(
            <<<'SQL'
                UPDATE subscribers
                SET
                    status = 'unsubscribed',
                    unsubscribed_at = :unsubscribed_at
                WHERE id = :id
                SQL,
        );

        $statement->execute([
            'id' => $id,
            'unsubscribed_at' => $unsubscribedAt,
        ]);
    }

    /**
     * Exécute une recherche et convertit le résultat en données PHP.
     *
     * @param string $sql       Requête SQL préparée.
     * @param string $parameter Nom du paramètre SQL.
     * @param string $value     Valeur du paramètre.
     *
     * @return array{
     *     id: int,
     *     email: string,
     *     status: string,
     *     confirm_token_expires_at: ?string
     * }|null
     */
    private function fetchOne(
        string $sql,
        string $parameter,
        string $value,
    ): ?array {
        $statement = $this->pdo->prepare($sql);

        $statement->execute([
            $parameter => $value,
        ]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false
            ? null
            : $this->hydrate($row);
    }

    /**
     * Convertit une ligne SQL en tableau d'abonné.
     *
     * @param array<string, mixed> $row
     *
     * @return array{
     *     id: int,
     *     email: string,
     *     status: string,
     *     confirm_token_expires_at: ?string
     * }
     */
    private function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'status' => (string) $row['status'],
            'confirm_token_expires_at' =>
                $row['confirm_token_expires_at'] === null
                    ? null
                    : (string) $row['confirm_token_expires_at'],
        ];
    }
}