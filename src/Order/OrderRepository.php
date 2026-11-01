<?php
declare(strict_types=1);
namespace Lumina\Order;
use PDO;
use Throwable;
final class OrderRepository implements OrderStore
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function createPending(array $order): void
    {
        $this->pdo->beginTransaction();
        try {
            // Un client qui a fermé la page de paiement peut réessayer tout de suite :
            // l'ancienne tentative est annulée, et non bloquée.
            $cancel = $this->pdo->prepare(<<<'SQL'
                UPDATE orders
                SET status = 'cancelled', cancelled_at = NOW()
                WHERE email = :email AND quantity = :quantity AND status = 'pending'
                  AND created_at >= (NOW() - INTERVAL 10 MINUTE)
                SQL);
            $cancel->execute(['email' => $order['email'], 'quantity' => $order['quantity']]);

            $this->insertPending($order);
            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function attachPayment(string $reference, string $provider, string $transactionId): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE orders
            SET payment_provider = :provider,
                payment_session_id = :transaction_id
            WHERE order_reference = :reference AND status = 'pending'
            SQL);
        $statement->execute([
            'provider' => $provider,
            'transaction_id' => $transactionId,
            'reference' => $reference,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('Commande introuvable ou déjà liée à un paiement.');
        }
    }

    public function markPaymentFailed(string $reference): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            UPDATE orders
            SET status = 'failed'
            WHERE order_reference = :reference AND status = 'pending'
            SQL);
        $statement->execute(['reference' => $reference]);
    }

    /** @param array<string, mixed> $order */
    private function insertPending(array $order): void
    {
        $statement = $this->pdo->prepare(<<<'SQL'
            INSERT INTO orders (
                order_reference, email, customer_name, shipping_address,
                city, state_code, postal_code, country_code, quantity,
                unit_price_cents, shipping_cents, total_cents, currency,
                total_xof, usd_to_eur_rate_scaled, rate_scale,
                eur_xof_rate_scaled, eur_xof_scale, rate_at,
                cgv_accepted_at, privacy_acknowledged_at
            ) VALUES (
                :reference, :email, :customer_name, :shipping_address,
                :city, :state_code, :postal_code, :country_code, :quantity,
                :unit_price, :shipping, :total, :currency,
                :total_xof, :usd_to_eur_rate_scaled, :rate_scale,
                :eur_xof_rate_scaled, :eur_xof_scale, :rate_at,
                :cgv_at, :privacy_at
            )
            SQL);
        $statement->execute([
            'reference' => $order['reference'],
            'email' => $order['email'],
            'customer_name' => $order['customer_name'],
            'shipping_address' => $order['shipping_address'],
            'city' => $order['city'],
            'state_code' => $order['state_code'],
            'postal_code' => $order['postal_code'],
            'country_code' => $order['country_code'],
            'quantity' => $order['quantity'],
            'unit_price' => $order['unit_price_cents'],
            'shipping' => $order['shipping_cents'],
            'total' => $order['total_cents'],
            'currency' => $order['currency'],
            'total_xof' => $order['total_xof'],
            'usd_to_eur_rate_scaled' => $order['usd_to_eur_rate_scaled'],
            'rate_scale' => $order['rate_scale'],
            'eur_xof_rate_scaled' => $order['eur_xof_rate_scaled'],
            'eur_xof_scale' => $order['eur_xof_scale'],
            'rate_at' => $order['rate_at'],
            'cgv_at' => $order['accepted_at'],
            'privacy_at' => $order['accepted_at'],
        ]);
    }
}