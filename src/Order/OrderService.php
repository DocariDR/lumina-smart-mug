<?php
declare(strict_types=1);
namespace Lumina\Order;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use RuntimeException;
use Lumina\Subscription\EmailValidator;
final class OrderService
{
    /** Seul pays livré pendant la précommande. */
    public const SHIPPING_COUNTRY = 'US';

    public function __construct(
        private readonly OrderStore $orders,
        private readonly array $product,
        private readonly CurrencyConverter $converter,
    ) {
    }
    /** @return array{reference:string, quantity:int, total_cents:int, total_xof:int, currency:string, rate:string} */
    public function create(array $input): array
    {
        $email = EmailValidator::normalize((string) ($input['email'] ?? ''));
        $name = $this->text($input['customer_name'] ?? '', 120);
        $address = $this->text($input['shipping_address'] ?? '', 255);
        $city = $this->text($input['city'] ?? '', 100);
        $state = strtoupper($this->text($input['state_code'] ?? '', 2));
        $zip = $this->text($input['postal_code'] ?? '', 10);
        $country = strtoupper($this->text($input['country_code'] ?? '', 2));
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
        $maxQuantity = (int) ($this->product['max_quantity'] ?? 3);

        // On valide tous les champs avant de répondre, pour que le client
        // corrige tout d'un coup au lieu d'un champ à la fois.
        $errors = [];
        if (!EmailValidator::isValid($email)) {
            $errors[] = 'email';
        }
        if ($name === '') {
            $errors[] = 'customer_name';
        }
        if ($address === '') {
            $errors[] = 'shipping_address';
        }
        if ($city === '') {
            $errors[] = 'city';
        }
        if (!UsStates::isValid($state)) {
            $errors[] = 'state_code';
        }
        if (!UsStates::isValidZip($zip)) {
            $errors[] = 'postal_code';
        }
        if ($country !== self::SHIPPING_COUNTRY) {
            $errors[] = 'country_code';
        }
        if (!is_int($quantity) || $quantity < 1 || $quantity > $maxQuantity) {
            $errors[] = 'quantity';
        }
        if (($input['cgv_accepted'] ?? '') !== '1') {
            $errors[] = 'cgv_accepted';
        }
        if (($input['privacy_acknowledged'] ?? '') !== '1') {
            $errors[] = 'privacy_acknowledged';
        }
        if ($errors !== []) {
            throw new OrderValidationException($errors);
        }

        $unitPrice = (int) ($this->product['unit_price_cents'] ?? 0);
        $shipping = (int) ($this->product['shipping_cents'] ?? 0);
        $currency = strtoupper((string) ($this->product['currency'] ?? 'USD'));
        if ($unitPrice < 1 || $shipping < 0 || $currency !== 'USD') {
            // Erreur de configuration, pas une saisie : ne doit pas être présentée au client comme une erreur de formulaire.
            throw new RuntimeException('Configuration produit invalide.');
        }

        // Même e-mail et même quantité déjà en attente il y a peu : double clic ou script.
        if ($this->orders->hasRecentPending($email, $quantity)) {
            throw new OrderDuplicateException('Commande identique déjà en attente.');
        }

        $acceptedAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $reference = strtoupper(bin2hex(random_bytes(8)));
        $total = ($quantity * $unitPrice) + $shipping;
        // Montant XOF calculé ici, une seule fois, et figé dans la commande.
        $conversion = $this->converter->convertUsdCentsToXof($total);

        try {
            $this->orders->createPending([
                'reference' => $reference,
                'email' => $email,
                'customer_name' => $name,
                'shipping_address' => $address,
                'city' => $city,
                'state_code' => $state,
                'postal_code' => $zip,
                'country_code' => $country,
                'quantity' => $quantity,
                'unit_price_cents' => $unitPrice,
                'shipping_cents' => $shipping,
                'total_cents' => $total,
                'currency' => $currency,
                'total_xof' => $conversion['total_xof'],
                'usd_to_eur_rate_scaled' => $conversion['rate_scaled'],
                'rate_scale' => $conversion['rate_scale'],
                'eur_xof_rate_scaled' => $conversion['eur_xof_rate_scaled'],
                'eur_xof_scale' => $conversion['eur_xof_scale'],
                'rate_at' => $conversion['rate_at'],
                'accepted_at' => $acceptedAt,
            ]);
        } catch (PDOException $exception) {
            error_log('Lumina : création de commande impossible : ' . $exception->getMessage());
            throw new RuntimeException('Création de commande impossible.', 0, $exception);
        }

        return [
            'reference' => $reference,
            'quantity' => $quantity,
            'total_cents' => $total,
            'total_xof' => $conversion['total_xof'],
            'currency' => $currency,
            'rate' => $this->converter->formattedRate(),
        ];
    }
    private function text(mixed $value, int $maxLength): string
    {
        $value = is_string($value) ? trim($value) : '';
        return mb_strlen($value) <= $maxLength ? $value : '';
    }
}